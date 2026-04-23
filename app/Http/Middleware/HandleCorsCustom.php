<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🔥 CORS Middleware - Versão Definitiva
 * 
 * REGRA: SEMPRE adiciona headers CORS, mesmo em erros 500
 * 
 * Pipeline:
 * 1. OPTIONS → responde 204 com headers CORS
 * 2. Outras requisições → try/catch garante CORS mesmo em exceção
 */
class HandleCorsCustom
{
    public function handle(Request $request, Closure $next): Response
    {
        \Log::debug('➡ HandleCorsCustom entrou', ['path' => $request->path(), 'method' => $request->method()]);

        // OPTIONS (preflight) → responde imediatamente
        if ($request->getMethod() === 'OPTIONS') {
            \Log::debug('⬅ HandleCorsCustom: OPTIONS preflight');
            return response('', 204)->withHeaders($this->headers($request));
        }

        // 🔥 CRÍTICO: try-catch para SEMPRE adicionar CORS
        try {
            $response = $next($request);
            \Log::debug('⬅ HandleCorsCustom: resposta OK', ['status' => $response->getStatusCode()]);
        } catch (\Throwable $e) {
            \Log::error('⬅ HandleCorsCustom: EXCEÇÃO CAPTURADA', ['error' => $e->getMessage()]);
            // Log do erro real (para debug)
            \Log::error('HandleCorsCustom capturou exceção', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
            ]);
            
            // Criar resposta de erro COM headers CORS
            $response = response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
                'error' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }

        // SEMPRE adicionar headers CORS na resposta
        // Usar headers->set() para compatibilidade com StreamedResponse
        foreach ($this->headers($request) as $key => $value) {
            $response->headers->set($key, $value);
        }
        
        return $response;
    }

    /**
     * Headers CORS baseados na config
     */
    private function headers(Request $request): array
    {
        $origin = $request->header('Origin');
        $allowedOrigin = self::resolveAllowedOrigin($origin);
        
        if (!$allowedOrigin) {
            return [];
        }

        $headers = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Empresa-ID, Accept, Origin',
            'Access-Control-Max-Age' => '86400',
        ];

        // Credentials só se não for wildcard
        if ($allowedOrigin !== '*' && config('cors.supports_credentials', false)) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // Exposed headers
        $exposed = config('cors.exposed_headers', []);
        if (!empty($exposed)) {
            $headers['Access-Control-Expose-Headers'] = is_array($exposed) ? implode(', ', $exposed) : $exposed;
        }

        return $headers;
    }

    /**
     * Origem que pode ser refletida em Access-Control-Allow-Origin (valor exato do header Origin).
     * Usado também pelo Exception Handler em bootstrap/app.php.
     */
    public static function resolveAllowedOrigin(?string $origin): ?string
    {
        if (!$origin) {
            return '*';
        }

        $allowed = config('cors.allowed_origins', ['*']);

        // Wildcard permite tudo
        if (in_array('*', $allowed, true)) {
            return $origin;
        }

        // Verificar lista exata (case-insensitive)
        $originLower = strtolower($origin);
        foreach ($allowed as $allowedOrigin) {
            if (strtolower((string) $allowedOrigin) === $originLower) {
                return $origin;
            }
        }

        // Verificar patterns (definir com modificador i em config/cors.php quando fizer sentido)
        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if ($pattern !== '' && @preg_match($pattern, $origin) === 1) {
                return $origin;
            }
        }

        return null;
    }
}
