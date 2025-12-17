<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RedisService;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRedis
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decaySeconds = 60): Response
    {
        $identifier = $this->resolveRequestSignature($request);
        
        if (!RedisService::isAvailable()) {
            // Se Redis não estiver disponível, permitir requisição
            return $next($request);
        }

        if (!RedisService::rateLimit($identifier, $maxAttempts, $decaySeconds)) {
            $remaining = RedisService::getRateLimitRemaining($identifier, $maxAttempts);
            
            return response()->json([
                'message' => 'Muitas requisições. Tente novamente mais tarde.',
                'retry_after' => $decaySeconds,
                'remaining' => $remaining,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => $remaining,
                'Retry-After' => $decaySeconds,
            ]);
        }

        $remaining = RedisService::getRateLimitRemaining($identifier, $maxAttempts);
        
        $response = $next($request);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
        ]);
    }

    /**
     * Resolve request signature (IP + endpoint)
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $endpoint = $request->path();
        $method = $request->method();
        
        return "rate_limit:{$ip}:{$method}:{$endpoint}";
    }
}
