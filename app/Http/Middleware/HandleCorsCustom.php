<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🔥 CORS Middleware - Versão Robusta
 * 
 * Responsabilidades:
 * 1. Responder OPTIONS (preflight) imediatamente com headers CORS
 * 2. Adicionar headers CORS em todas as respostas (incluindo erros 500)
 */
class HandleCorsCustom
{
    /**
     * Cache das configurações CORS
     */
    private ?array $config = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = $this->getConfig();
        $origin = $request->header('Origin');
        $allowedOrigin = $this->resolveAllowedOrigin($origin, $config);
        
        // Se for OPTIONS (preflight), responder imediatamente
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($allowedOrigin, $config);
        }
        
        // 🔥 CRÍTICO: Capturar exceções para garantir CORS mesmo em erro 500
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Criar resposta de erro 500 COM CORS
            $response = response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
                'file' => config('app.debug') ? $e->getFile() : null,
                'line' => config('app.debug') ? $e->getLine() : null,
            ], 500);
            
            // Log do erro
            \Log::error('CORS: erro 500 capturado', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
            ]);
        }
        
        // Adicionar headers CORS na resposta (sempre)
        return $this->addCorsHeaders($response, $allowedOrigin, $config);
    }

    /**
     * Responder requisição OPTIONS (preflight)
     */
    private function handlePreflight(?string $allowedOrigin, array $config): Response
    {
        $response = response('', 204);
        
        if ($allowedOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', $this->formatMethods($config['allowed_methods']));
            $response->headers->set('Access-Control-Allow-Headers', $this->formatHeaders($config['allowed_headers']));
            $response->headers->set('Access-Control-Max-Age', (string) ($config['max_age'] ?? 0));
            
            if ($config['supports_credentials'] && $allowedOrigin !== '*') {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }
        
        return $response;
    }

    /**
     * Adicionar headers CORS à resposta
     */
    public function addCorsHeaders(Response $response, ?string $allowedOrigin = null, ?array $config = null): Response
    {
        if ($allowedOrigin === null) {
            $config = $config ?? $this->getConfig();
            $origin = request()->header('Origin');
            $allowedOrigin = $this->resolveAllowedOrigin($origin, $config);
        }
        
        $config = $config ?? $this->getConfig();
        
        if ($allowedOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', $this->formatMethods($config['allowed_methods']));
            $response->headers->set('Access-Control-Allow-Headers', $this->formatHeaders($config['allowed_headers']));
            
            if ($config['supports_credentials'] && $allowedOrigin !== '*') {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            if (!empty($config['exposed_headers'])) {
                $exposed = is_array($config['exposed_headers']) 
                    ? implode(', ', $config['exposed_headers']) 
                    : $config['exposed_headers'];
                $response->headers->set('Access-Control-Expose-Headers', $exposed);
            }
        }
        
        return $response;
    }

    /**
     * Resolver origem permitida
     */
    private function resolveAllowedOrigin(?string $origin, array $config): ?string
    {
        $allowedOrigins = $config['allowed_origins'];
        
        if (in_array('*', $allowedOrigins)) {
            return $origin ?: '*';
        }
        
        if (!$origin) {
            return null;
        }
        
        $originLower = strtolower($origin);
        foreach ($allowedOrigins as $allowed) {
            if (strtolower($allowed) === $originLower) {
                return $origin;
            }
        }
        
        foreach ($config['allowed_origins_patterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $origin;
            }
        }
        
        return null;
    }

    private function formatMethods(array $methods): string
    {
        if (in_array('*', $methods)) {
            return 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        }
        return implode(', ', $methods);
    }

    private function formatHeaders(array $headers): string
    {
        if (in_array('*', $headers)) {
            return 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Empresa-ID, Accept, Origin';
        }
        return implode(', ', $headers);
    }

    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = [
                'allowed_origins' => config('cors.allowed_origins', ['*']),
                'allowed_origins_patterns' => config('cors.allowed_origins_patterns', []),
                'allowed_methods' => config('cors.allowed_methods', ['*']),
                'allowed_headers' => config('cors.allowed_headers', ['*']),
                'exposed_headers' => config('cors.exposed_headers', []),
                'max_age' => config('cors.max_age', 0),
                'supports_credentials' => config('cors.supports_credentials', false),
            ];
        }
        return $this->config;
    }
}
