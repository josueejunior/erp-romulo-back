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
            'tenancy' => \App\Http\Middleware\InitializeTenancyByRequestData::class,
            'rate.limit.redis' => \App\Http\Middleware\RateLimitRedis::class,
        ]);
        
        // Configurar CORS para React
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
