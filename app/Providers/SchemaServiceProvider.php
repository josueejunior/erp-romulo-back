<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use App\Database\Schema\Blueprint;

/**
 * Service Provider para configurações de Schema
 * Registra o Blueprint customizado para uso nas migrations
 */
class SchemaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configurar comprimento padrão de strings
        Builder::defaultStringLength(191);
        Schema::defaultStringLength(191);
        
        // Registrar o Blueprint customizado como resolver padrão para todas as conexões
        $this->app->afterResolving('db', function ($db) {
            $db->getSchemaBuilder()->blueprintResolver(function ($table, $callback = null, $prefix = '') {
                return new Blueprint($table, $callback, $prefix);
            });
        });
    }
}

