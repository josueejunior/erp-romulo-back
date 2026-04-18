<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

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
            \Log::warning('Model não encontrado', [
                'model' => class_basename($e->getModel()),
                'url' => $request->fullUrl(),
            ]);
            
            $response = response()->json([
                'message' => 'Recurso não encontrado',
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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            $response = response()->json([
                'message' => config('app.debug') 
                    ? $e->getMessage() 
                    : 'Erro interno do servidor',
            ], 500);
            
            // 🔥 ARQUITETURA LIMPA: Usar método centralizado do HandleCorsCustom
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
        }
    }
}

