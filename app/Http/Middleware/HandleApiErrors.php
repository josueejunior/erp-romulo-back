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
     * Adicionar headers CORS à resposta usando a mesma lógica do HandleCors
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        
        // Verificar se a origem está permitida
        $isAllowed = false;
        if ($origin) {
            // Verificar se está na lista de origens permitidas
            if (in_array($origin, $allowedOrigins)) {
                $isAllowed = true;
            }
            // Verificar padrões (se houver)
            foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
        }
        
        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            if (config('cors.supports_credentials', false)) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods', ['*'])));
            $allowedHeaders = config('cors.allowed_headers', ['*']);
            if (is_array($allowedHeaders)) {
                $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
            } else {
                $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders);
            }
        }
        
        return $response;
    }
}

