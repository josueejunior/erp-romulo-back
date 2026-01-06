<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🔥 CORS Middleware - Versão Robusta e Limpa
 * 
 * Responsabilidades:
 * 1. Responder OPTIONS (preflight) imediatamente com headers CORS
 * 2. Adicionar headers CORS em todas as respostas
 * 
 * Design:
 * - Simples e rápido
 * - Logs apenas em erros ou debug
 * - Não depende de route estar resolvida
 * - Não captura exceções (deixa para o Exception Handler)
 */
class HandleCorsCustom
{
    /**
     * Cache das configurações CORS (evita ler config múltiplas vezes)
     */
    private ?array $config = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Carregar config uma vez
        $config = $this->getConfig();
        
        // Verificar origem
        $origin = $request->header('Origin');
        $allowedOrigin = $this->resolveAllowedOrigin($origin, $config);
        
        // Se for OPTIONS (preflight), responder imediatamente
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($allowedOrigin, $config);
        }
        
        // Processar requisição normal
        $response = $next($request);
        
        // Adicionar headers CORS na resposta
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
     * 
     * Método público para ser usado pelo Exception Handler quando necessário
     */
    public function addCorsHeaders(Response $response, ?string $allowedOrigin = null, ?array $config = null): Response
    {
        // Se não passou allowedOrigin, resolver do request
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
            
            // Exposed headers
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
     * 
     * @return string|null Origem permitida ou null se não permitida
     */
    private function resolveAllowedOrigin(?string $origin, array $config): ?string
    {
        $allowedOrigins = $config['allowed_origins'];
        
        // Se permite todas as origens
        if (in_array('*', $allowedOrigins)) {
            // Se tem origem, usar ela; senão usar '*'
            return $origin ?: '*';
        }
        
        // Se não tem origem no request, não adicionar CORS
        if (!$origin) {
            return null;
        }
        
        // Verificar se origem está na lista (case-insensitive)
        $originLower = strtolower($origin);
        foreach ($allowedOrigins as $allowed) {
            if (strtolower($allowed) === $originLower) {
                return $origin; // Retornar com case original
            }
        }
        
        // Verificar padrões regex
        foreach ($config['allowed_origins_patterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $origin;
            }
        }
        
        // Origem não permitida - logar apenas em debug
        if (config('app.debug')) {
            \Log::debug('CORS: origem não permitida', [
                'origin' => $origin,
                'allowed' => $allowedOrigins,
            ]);
        }
        
        return null;
    }

    /**
     * Formatar métodos permitidos
     */
    private function formatMethods(array $methods): string
    {
        if (in_array('*', $methods)) {
            return 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        }
        return implode(', ', $methods);
    }

    /**
     * Formatar headers permitidos
     */
    private function formatHeaders(array $headers): string
    {
        if (in_array('*', $headers)) {
            return 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Empresa-ID, Accept, Origin';
        }
        return implode(', ', $headers);
    }

    /**
     * Obter configuração CORS (com cache)
     */
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
