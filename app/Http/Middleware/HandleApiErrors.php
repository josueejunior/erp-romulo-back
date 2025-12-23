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
            
            return response()->json([
                'message' => 'Muitas requisições. Por favor, aguarde alguns instantes antes de tentar novamente.',
                'retry_after' => (int) $retryAfter,
                'retry_after_seconds' => (int) $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $headers['X-RateLimit-Limit'] ?? '120',
                'X-RateLimit-Remaining' => $headers['X-RateLimit-Remaining'] ?? '0',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            \Log::warning('Model não encontrado', [
                'model' => class_basename($e->getModel()),
                'url' => $request->fullUrl(),
            ]);
            
            return response()->json([
                'message' => 'Recurso não encontrado',
            ], 404);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => 'Não autenticado',
            ], 401);
        } catch (\Exception $e) {
            \Log::error('Erro não tratado na API', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            return response()->json([
                'message' => config('app.debug') 
                    ? $e->getMessage() 
                    : 'Erro interno do servidor',
            ], 500);
        }
    }
}

