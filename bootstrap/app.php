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
        $middleware->alias([
            'empresa.ativa' => \App\Http\Middleware\EnsureEmpresaAtiva::class,
            'empresa.context' => \App\Http\Middleware\EnsureEmpresaAtivaContext::class,
            'tenancy' => \App\Http\Middleware\InitializeTenancyByRequestData::class,
            'rate.limit.redis' => \App\Http\Middleware\RateLimitRedis::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'jwt.auth' => \App\Http\Middleware\AuthenticateJWT::class,
        ]);
        
        // CORS DEVE ser o PRIMEIRO middleware - executar globalmente antes de tudo
        // Isso garante que requisições OPTIONS sejam processadas antes de qualquer outro middleware
        $middleware->prepend(\App\Http\Middleware\HandleCorsCustom::class);
        
        // HandleApiErrors após CORS para rotas de API
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleApiErrors::class,
        ]);
        
        // Headers de segurança apenas para rotas web (não interfere com API/CORS)
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // IMPORTANTE:
        // EnsureEmpresaAtivaContext NÃO deve rodar como middleware global.
        // Ele depende de auth/tenancy e deve ser aplicado apenas nas rotas autenticadas.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 🔥 ARQUITETURA LIMPA: Usar HandleCorsCustom como único responsável por CORS
        // Helper que delega para o middleware centralizado
        $addCorsToResponse = function ($response, $request) {
            if (!$request->expectsJson() || !($response instanceof \Symfony\Component\HttpFoundation\Response)) {
                return $response;
            }
            
            // Delegar para HandleCorsCustom (único dono da lógica CORS)
            return app(\App\Http\Middleware\HandleCorsCustom::class)->addCorsHeaders($request, $response);
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
