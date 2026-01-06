<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware customizado para garantir que CORS funcione corretamente
 * Especialmente para requisições OPTIONS (preflight)
 */
class HandleCorsCustom
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $method = $request->method();
        $url = $request->fullUrl();
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        $envCorsValue = env('CORS_ALLOWED_ORIGINS', 'NOT_SET');
        
        // Log inicial da requisição
        \Log::info('HandleCorsCustom - Requisição recebida', [
            'method' => $method,
            'url' => $url,
            'origin' => $origin,
            'env_CORS_ALLOWED_ORIGINS' => $envCorsValue,
            'config_cors_allowed_origins' => $allowedOrigins,
            'user_agent' => $request->header('User-Agent'),
            'request_headers' => $request->headers->all(),
        ]);
        
        // Verificar se permite todas as origens
        $allowAll = in_array('*', $allowedOrigins);
        
        // Verificar se a origem está permitida
        $isAllowed = false;
        $allowedOrigin = null;
        
        if ($allowAll) {
            // Se permite todas as origens, usar a origem específica se disponível
            // Caso contrário, usar '*'
            $isAllowed = true;
            if ($origin) {
                $allowedOrigin = $origin;
            } else {
                $allowedOrigin = '*';
            }
            \Log::info('HandleCorsCustom - Permite todas as origens', [
                'allowed_origin' => $allowedOrigin,
            ]);
        } elseif ($origin) {
            // Verificar se está na lista de origens permitidas (comparação case-insensitive)
            $allowedOriginsNormalized = array_map('strtolower', $allowedOrigins);
            $originNormalized = strtolower($origin);
            
            if (in_array($originNormalized, $allowedOriginsNormalized)) {
                $isAllowed = true;
                $allowedOrigin = $origin; // Usar a origem original (com case original)
                \Log::info('HandleCorsCustom - Origem permitida na lista', [
                    'origin' => $origin,
                    'allowed_origin' => $allowedOrigin,
                ]);
            } else {
                // Verificar padrões (se houver)
                foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
                    if (preg_match($pattern, $origin)) {
                        $isAllowed = true;
                        $allowedOrigin = $origin;
                        \Log::info('HandleCorsCustom - Origem permitida por padrão', [
                            'origin' => $origin,
                            'pattern' => $pattern,
                        ]);
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    \Log::warning('HandleCorsCustom - Origem NÃO permitida', [
                        'origin' => $origin,
                        'allowed_origins' => $allowedOrigins,
                    ]);
                }
            }
        } else {
            // Sem header Origin - permitir apenas se allowAll
            $isAllowed = $allowAll;
            if ($isAllowed) {
                $allowedOrigin = '*';
            }
            \Log::info('HandleCorsCustom - Sem header Origin na requisição', [
                'is_allowed' => $isAllowed,
            ]);
        }
        
        // Se for requisição OPTIONS (preflight), responder imediatamente
        if ($request->isMethod('OPTIONS')) {
            \Log::info('HandleCorsCustom - Requisição OPTIONS (preflight) detectada');
            
            $response = response('', 204);
            
            // Sempre adicionar headers CORS se permitir todas as origens ou se a origem estiver permitida
            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
                $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
                $response->headers->set('Access-Control-Max-Age', config('cors.max_age', 0));
                
                // Só adicionar credentials se não for origem * e se configurado
                if (config('cors.supports_credentials', false) && !$allowAll) {
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                }
                
                \Log::info('HandleCorsCustom - Headers CORS adicionados na resposta OPTIONS', [
                    'Access-Control-Allow-Origin' => $allowedOrigin,
                    'Access-Control-Allow-Methods' => $this->getAllowedMethods(),
                    'Access-Control-Allow-Headers' => $this->getAllowedHeaders(),
                    'response_headers' => $response->headers->all(),
                ]);
            } else {
                \Log::error('HandleCorsCustom - Headers CORS NÃO adicionados - origem não permitida', [
                    'origin' => $origin,
                    'is_allowed' => $isAllowed,
                ]);
            }
            
            return $response;
        }
        
        // Para outras requisições, processar normalmente e adicionar headers CORS
        \Log::info('HandleCorsCustom - Processando requisição normal', [
            'method' => $method,
        ]);
        
        try {
            \Log::debug('HandleCorsCustom - Chamando $next($request)', [
                'path' => $request->path(),
                'method' => $request->method(),
                'route' => $request->route() ? $request->route()->getName() : 'NO_ROUTE',
                'route_action' => $request->route() ? $request->route()->getActionName() : 'NO_ACTION',
            ]);
            
            // Usar set_time_limit para evitar timeout silencioso
            set_time_limit(30);
            
            // Registrar tempo antes de chamar $next
            $startTime = microtime(true);
            
            $response = $next($request);
            
            $elapsedTime = microtime(true) - $startTime;
            
            \Log::debug('HandleCorsCustom - $next($request) retornou', [
                'status_code' => $response ? $response->getStatusCode() : 'NULL',
                'response_type' => $response ? get_class($response) : 'NULL',
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
        } catch (\Throwable $e) {
            \Log::error('HandleCorsCustom - Exceção capturada', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            // Criar resposta de erro e adicionar CORS usando método centralizado
            $errorResponse = response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
            ], 500);
            
            // Adicionar headers CORS usando método centralizado
            $errorResponse = $this->addCorsHeaders($request, $errorResponse);
            
            \Log::info('HandleCorsCustom - Headers CORS adicionados na resposta de erro', [
                'has_cors_origin' => $errorResponse->headers->has('Access-Control-Allow-Origin'),
            ]);
            
            return $errorResponse;
        } catch (\Error $e) {
            // Capturar erros fatais do PHP (PHP 7+)
            \Log::error('HandleCorsCustom - Erro fatal capturado', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            // Criar resposta de erro e adicionar CORS usando método centralizado
            $errorResponse = response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
            ], 500);
            
            // Adicionar headers CORS usando método centralizado
            $errorResponse = $this->addCorsHeaders($request, $errorResponse);
            
            return $errorResponse;
        }
        
        // Adicionar headers CORS na resposta normal usando método centralizado
        $response = $this->addCorsHeaders($request, $response);
        
        \Log::info('HandleCorsCustom - Headers CORS adicionados na resposta normal', [
            'status_code' => $response->getStatusCode(),
            'has_cors_origin' => $response->headers->has('Access-Control-Allow-Origin'),
        ]);
        
        return $response;
    }
    
    /**
     * Adicionar headers CORS à resposta
     * 
     * 🔥 MÉTODO PÚBLICO: Único lugar que adiciona CORS headers
     * Pode ser chamado por outros middlewares/handlers quando necessário
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        $allowAll = in_array('*', $allowedOrigins);
        
        // Verificar se a origem está permitida
        $isAllowed = false;
        $allowedOrigin = null;
        
        if ($allowAll) {
            $isAllowed = true;
            $allowedOrigin = $origin ?: '*';
        } elseif ($origin) {
            // Comparação case-insensitive
            $allowedOriginsNormalized = array_map('strtolower', $allowedOrigins);
            $originNormalized = strtolower($origin);
            
            if (in_array($originNormalized, $allowedOriginsNormalized)) {
                $isAllowed = true;
                $allowedOrigin = $origin;
            } else {
                // Verificar padrões (se houver)
                foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
                    if (preg_match($pattern, $origin)) {
                        $isAllowed = true;
                        $allowedOrigin = $origin;
                        break;
                    }
                }
            }
        }
        
        if ($isAllowed && $response instanceof Response) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
            $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
            
            // Só adicionar credentials se não for origem * e se configurado
            if (config('cors.supports_credentials', false) && !$allowAll) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            // Exposed headers
            $exposedHeaders = config('cors.exposed_headers', []);
            if (!empty($exposedHeaders)) {
                if (is_array($exposedHeaders)) {
                    $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
                } else {
                    $response->headers->set('Access-Control-Expose-Headers', $exposedHeaders);
                }
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
            // Se contém '*', retornar métodos comuns
            if (in_array('*', $allowedMethods)) {
                return 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
            }
            return implode(', ', $allowedMethods);
        }
        // Se for string e for '*', retornar métodos comuns
        if ($allowedMethods === '*') {
            return 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
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
            // Se contém '*', retornar headers comuns
            if (in_array('*', $allowedHeaders)) {
                return 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Empresa-ID, Accept, Origin';
            }
            return implode(', ', $allowedHeaders);
        }
        // Se for string e for '*', retornar headers comuns
        if ($allowedHeaders === '*') {
            return 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Empresa-ID, Accept, Origin';
        }
        return $allowedHeaders;
    }
}

