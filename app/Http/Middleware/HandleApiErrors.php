<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Services\DiscordErrorNotifier;

/**
 * 🔥 CAMADA 2 - Error Boundary
 * 
 * Responsabilidade ÚNICA: Transformar exceptions em JSON amigável
 * 
 * ✅ Faz:
 * - Captura exceções do pipeline
 * - Traduz exceptions em JSON
 * - Adiciona headers CORS nas respostas de erro
 * 
 * ❌ NUNCA faz:
 * - Decisões de fluxo
 * - Autenticação
 * - Validação
 * - Lógica de negócio
 * 
 * 📌 Nota: Deve rodar como prepend para capturar exceções de todos os middlewares seguintes
 */
class HandleApiErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 🔥 DEBUG: Log todas as requisições POST para formacao-preco
        if ($request->method() === 'POST' && str_contains($request->path(), 'formacao-preco')) {
            \Log::info('HandleApiErrors - Requisição POST para formacao-preco detectada', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'route_parameters' => $request->route()?->parameters(),
                'request_data' => $request->all(),
            ]);
        }
        
        try {
            $response = $next($request);
            
            // Log erros 5xx
            if ($response->getStatusCode() >= 500) {
                \Log::error('Erro 5xx na API', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'user_id' => auth()->id(),
                ]);

                // Disparar alerta para Discord
                DiscordErrorNotifier::notifyHttpError($request, $response->getStatusCode());
            }
            
            // 🔥 DEBUG: Log respostas 400 para formacao-preco
            if ($response->getStatusCode() === 400 && str_contains($request->path(), 'formacao-preco')) {
                \Log::error('HandleApiErrors - Resposta 400 para formacao-preco', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'response_content' => $response->getContent(),
                ]);
            }
            
            return $response;
        } catch (ThrottleRequestsException $e) {
            $headers = $e->getHeaders();
            $retryAfter = $headers['Retry-After'] ?? 60;
            
            \Log::warning('Rate limit excedido', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'retry_after' => $retryAfter,
                'headers' => $headers,
            ]);
            
            $message = 'Muitas requisições. Por favor, aguarde ' . (int) $retryAfter . ' segundo(s) antes de tentar novamente.';
            if ($retryAfter >= 60) {
                $minutes = round($retryAfter / 60);
                $message = "Muitas requisições. Por favor, aguarde {$minutes} minuto(s) antes de tentar novamente.";
            }
            
            $response = response()->json([
                'message' => $message,
                'error' => 'Too Many Attempts.',
                'retry_after' => (int) $retryAfter,
                'retry_after_seconds' => (int) $retryAfter,
                'success' => false,
            ], 429)->withHeaders([
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) ($headers['X-RateLimit-Limit'] ?? '200'),
                'X-RateLimit-Remaining' => (string) ($headers['X-RateLimit-Remaining'] ?? '0'),
            ]);
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            
            // Log detalhado dos erros de validação
            \Log::warning('Erro de validação capturado no middleware', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'errors' => $errors,
                'fields' => array_keys($errors),
                'user_id' => auth()->id(),
                'data' => $request->all(),
            ]);
            
            $response = response()->json([
                'message' => 'Dados inválidos',
                'errors' => $errors,
            ], 422);
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        } catch (ModelNotFoundException $e) {
            \Log::error('ModelNotFoundException capturada no HandleApiErrors', [
                'model' => class_basename($e->getModel()),
                'model_class' => $e->getModel(),
                'ids' => $e->getIds(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'route_parameters' => $request->route()?->parameters(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $response = response()->json([
                'message' => 'Recurso não encontrado: ' . class_basename($e->getModel()),
            ], 404);
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        } catch (AuthenticationException $e) {
            $response = response()->json([
                'message' => 'Não autenticado',
            ], 401);
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        } catch (\Exception $e) {
            \Log::error('Erro não tratado na API', [
                'message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'route_parameters' => $request->route()?->parameters(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            // Se for DomainException, retornar 400 em vez de 500
            if ($e instanceof \App\Domain\Exceptions\DomainException) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'DOMAIN_ERROR',
                ], 400);
            } else {
                $response = response()->json([
                    'message' => config('app.debug') 
                        ? $e->getMessage() 
                        : 'Erro interno do servidor',
                ], 500);

                // Disparar alerta para Discord para erros 500 internos
                DiscordErrorNotifier::notifyHttpError($request, 500, $e);
            }
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        }
    }
}

