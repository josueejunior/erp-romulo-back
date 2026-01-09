<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configurar redirectTo para rotas de autenticação
        // Para rotas web, redirecionar para /login (URL direta)
        // Para APIs, retornar JSON 401 (manipulado pelo Exception Handler)
        $middleware->redirectGuestsTo('/login');
        
        $middleware->alias([
            // 🔥 Nova arquitetura (em uso)
            'jwt.auth' => \App\Http\Middleware\AuthenticateJWT::class,
            'auth.optional' => \App\Http\Middleware\OptionalAuthenticate::class, // Autenticação opcional - tenta autenticar se houver token, mas não bloqueia
            'auth.context' => \App\Http\Middleware\BuildAuthContext::class,
            'tenant.context' => \App\Http\Middleware\ResolveTenantContext::class,
            'bootstrap.context' => \App\Http\Middleware\BootstrapApplicationContext::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'assinatura.ativa' => \App\Http\Middleware\EnsureTenantHasActiveSubscription::class,
            'onboarding.completo' => \App\Http\Middleware\CheckOnboarding::class,
            
            // Middlewares de segurança e robustez
            'sanitize.inputs' => \App\Http\Middleware\SanitizeInputs::class,
            
            // Middlewares legados (deprecated - não usar)
            'empresa.ativa' => \App\Http\Middleware\EnsureEmpresaAtiva::class,
            'empresa.context' => \App\Http\Middleware\EnsureEmpresaAtivaContext::class,
            'tenancy' => \App\Http\Middleware\InitializeTenancyByRequestData::class,
            'rate.limit.redis' => \App\Http\Middleware\RateLimitRedis::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // ✅ CORS como GLOBAL PREPEND
        // Roda PRIMEIRO (antes de tudo) e processa resposta por ÚLTIMO
        // Isso garante que QUALQUER erro seja capturado pelo try-catch do CORS
        $middleware->prepend(\App\Http\Middleware\HandleCorsCustom::class);
        
        // Headers de segurança apenas para rotas web (não interfere com API/CORS)
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // IMPORTANTE:
        // EnsureEmpresaAtivaContext NÃO deve rodar como middleware global.
        // Ele depende de auth/tenancy e deve ser aplicado apenas nas rotas autenticadas.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ✅ Helper simples para CORS no Exception Handler
        // Middleware ≠ Exception handler - cada um com sua responsabilidade
        $addCorsToResponse = function ($response, $request) {
            if (!$request->expectsJson() || !($response instanceof \Symfony\Component\HttpFoundation\Response)) {
                return $response;
            }
            
            $origin = $request->headers->get('Origin');
            if (!$origin) {
                return $response;
            }
            
            // Verificar origem permitida
            $allowedOrigins = config('cors.allowed_origins', ['*']);
            $allowAll = in_array('*', $allowedOrigins);
            $isAllowed = $allowAll;
            $allowedOrigin = $origin;
            
            if (!$allowAll && $origin) {
                $allowedOriginsNormalized = array_map('strtolower', $allowedOrigins);
                $originNormalized = strtolower($origin);
                $isAllowed = in_array($originNormalized, $allowedOriginsNormalized);
            }
            
            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
                $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
            }
            
            return $response;
        };
        
        // Exceções de Domínio - Bad Request (400)
        $exceptions->render(function (\App\Domain\Exceptions\DomainException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'DOMAIN_ERROR',
                ], 400);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções de Validação de Domínio - Unprocessable Entity (422)
        $exceptions->render(function (\App\Domain\Exceptions\ValidationException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors,
                    'code' => 'VALIDATION_ERROR',
                ], 422);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções de Regra de Negócio - Bad Request com detalhes (400)
        $exceptions->render(function (\App\Domain\Exceptions\BusinessRuleException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'rule' => $e->rule,
                    'context' => $e->context,
                    'code' => 'BUSINESS_RULE_VIOLATION',
                ], 400);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // 🔥 Throttle Requests Exception - Rate Limit (429)
        // Garantir que sempre retorne JSON amigável, mesmo se não for capturado pelo HandleApiErrors
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $headers = $e->getHeaders();
                $retryAfter = $headers['Retry-After'] ?? 60;
                
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
                
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções de Não Encontrado - Not Found (404)
        $exceptions->render(function (\App\Domain\Exceptions\NotFoundException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'NOT_FOUND',
                ], 404);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções de Não Autorizado - Forbidden (403)
        $exceptions->render(function (\App\Domain\Exceptions\UnauthorizedException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'UNAUTHORIZED',
                ], 403);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções de Validação do Laravel - Unprocessable Entity (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) use ($addCorsToResponse) {
            if ($request->expectsJson()) {
                $errors = $e->errors();
                
                // Log detalhado dos erros de validação
                \Log::warning('Erro de validação do Laravel capturado no exception handler', [
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
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Exceções genéricas não tratadas - Internal Server Error (500)
        $exceptions->render(function (\Throwable $e, $request) use ($addCorsToResponse) {
            // Logar TODAS as exceções não tratadas, mesmo que não sejam JSON
            \Log::error('Exceção não tratada no Exception Handler', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'class' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'previous' => $e->getPrevious() ? [
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ] : null,
            ]);
            
            if ($request->expectsJson() && !($e instanceof \Illuminate\Validation\ValidationException)) {
                $response = response()->json([
                    'message' => config('app.debug') 
                        ? $e->getMessage() 
                        : 'Erro interno do servidor',
                    'file' => config('app.debug') ? $e->getFile() : null,
                    'line' => config('app.debug') ? $e->getLine() : null,
                ], 500);
                return $addCorsToResponse($response, $request);
            }
        });
        
        // Logar exceções não tratadas para debugging
        $exceptions->report(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
