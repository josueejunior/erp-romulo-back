<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class HandleApiErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('HandleApiErrors::handle - Iniciando', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);
        
        try {
            $response = $next($request);
            
            \Log::debug('HandleApiErrors::handle - Resposta recebida', [
                'status' => $response->getStatusCode(),
            ]);
            
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
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $headers['X-RateLimit-Limit'] ?? '120',
                'X-RateLimit-Remaining' => $headers['X-RateLimit-Remaining'] ?? '0',
            ]);
            
            return $this->addCorsHeaders($request, $response);
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
            
            return $this->addCorsHeaders($request, $response);
        } catch (ModelNotFoundException $e) {
            \Log::warning('Model não encontrado', [
                'model' => class_basename($e->getModel()),
                'url' => $request->fullUrl(),
            ]);
            
            $response = response()->json([
                'message' => 'Recurso não encontrado',
            ], 404);
            
            return $this->addCorsHeaders($request, $response);
        } catch (AuthenticationException $e) {
            $response = response()->json([
                'message' => 'Não autenticado',
            ], 401);
            
            return $this->addCorsHeaders($request, $response);
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
            
            return $this->addCorsHeaders($request, $response);
        }
    }
    
    /**
     * Adicionar headers CORS à resposta usando a mesma lógica do HandleCorsCustom
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        
        // Verificar se permite todas as origens (mesma lógica do HandleCorsCustom)
        $allowAll = in_array('*', $allowedOrigins);
        
        // Verificar se a origem está permitida
        $isAllowed = $allowAll;
        $allowedOrigin = '*';
        
        if ($allowAll) {
            // Se permite todas as origens, usar a origem específica se disponível
            if ($origin) {
                $allowedOrigin = $origin;
            }
        } elseif ($origin) {
            // Verificar se está na lista de origens permitidas
            if (in_array($origin, $allowedOrigins)) {
                $isAllowed = true;
                $allowedOrigin = $origin;
            }
            // Verificar padrões (se houver)
            foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    $allowedOrigin = $origin;
                    break;
                }
            }
        }
        
        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
            $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
            
            // Só adicionar credentials se não for origem * e se configurado
            if (config('cors.supports_credentials', false) && !$allowAll) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }
        
        return $response;
    }
    
    /**
     * Obter métodos permitidos formatados
     */
    protected function getAllowedMethods(): string
    {
        $allowedMethods = config('cors.allowed_methods', ['*']);
        if (is_array($allowedMethods)) {
            return implode(', ', $allowedMethods);
        }
        return $allowedMethods;
    }
    
    /**
     * Obter headers permitidos formatados
     */
    protected function getAllowedHeaders(): string
    {
        $allowedHeaders = config('cors.allowed_headers', ['*']);
        if (is_array($allowedHeaders)) {
            return implode(', ', $allowedHeaders);
        }
        return $allowedHeaders;
    }
}

