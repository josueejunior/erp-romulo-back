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
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        
        // Verificar se permite todas as origens
        $allowAll = in_array('*', $allowedOrigins);
        
        // Verificar se a origem está permitida
        $isAllowed = $allowAll;
        $allowedOrigin = '*';
        
        if ($allowAll) {
            // Se permite todas as origens, usar a origem específica se disponível
            // Caso contrário, usar '*'
            if ($origin) {
                $allowedOrigin = $origin;
            }
        } elseif ($origin) {
            // Verificar se está na lista de origens permitidas
            if (in_array($origin, $allowedOrigins)) {
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
        
        // Se for requisição OPTIONS (preflight), responder imediatamente
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            
            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
                $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
                $response->headers->set('Access-Control-Max-Age', config('cors.max_age', 0));
                
                // Só adicionar credentials se não for origem *
                if (config('cors.supports_credentials', false) && !$allowAll) {
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                }
            }
            
            return $response;
        }
        
        // Para outras requisições, processar normalmente e adicionar headers CORS
        $response = $next($request);
        
        if ($isAllowed && $response instanceof Response) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', $this->getAllowedMethods());
            $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
            
            // Só adicionar credentials se não for origem *
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

