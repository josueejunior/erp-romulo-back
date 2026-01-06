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
        ]);
        
        // Configurar CORS para React - DEVE rodar PRIMEIRO para preflight OPTIONS
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // HandleApiErrors após CORS
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleApiErrors::class,
        ]);
        
        // Headers de segurança apenas para rotas web (não interfere com API/CORS)
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // Middleware de contexto de empresa deve rodar APÓS autenticação
        // Usar append para rodar após todos os middlewares padrão (incluindo auth)
        $middleware->append(\App\Http\Middleware\EnsureEmpresaAtivaContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exceções de Domínio - Bad Request (400)
        $exceptions->render(function (\App\Domain\Exceptions\DomainException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'DOMAIN_ERROR',
                ], 400);
            }
        });
        
        // Exceções de Validação de Domínio - Unprocessable Entity (422)
        $exceptions->render(function (\App\Domain\Exceptions\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors,
                    'code' => 'VALIDATION_ERROR',
                ], 422);
            }
        });
        
        // Exceções de Regra de Negócio - Bad Request com detalhes (400)
        $exceptions->render(function (\App\Domain\Exceptions\BusinessRuleException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'rule' => $e->rule,
                    'context' => $e->context,
                    'code' => 'BUSINESS_RULE_VIOLATION',
                ], 400);
            }
        });
        
        // Exceções de Não Encontrado - Not Found (404)
        $exceptions->render(function (\App\Domain\Exceptions\NotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });
        
        // Exceções de Não Autorizado - Forbidden (403)
        $exceptions->render(function (\App\Domain\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'UNAUTHORIZED',
                ], 403);
            }
        });
        
        // Exceções de Validação do Laravel - Unprocessable Entity (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
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
                
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $errors,
                ], 422);
            }
        });
        
        // Logar exceções não tratadas para debugging
        $exceptions->report(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
