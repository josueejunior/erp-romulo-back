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
        // Configurar o resolver de Blueprint antes de qualquer uso
        $this->app->resolving('db.schema', function ($schema) {
            $schema->blueprintResolver(function ($table, $callback = null, $prefix = '') {
                return new Blueprint($table, $callback, $prefix);
            });
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configurar comprimento padrão de strings
        Builder::defaultStringLength(191);
        Schema::defaultStringLength(191);
        
        // Garantir que o resolver está configurado na conexão padrão
        try {
            Schema::getConnection()
                ->getSchemaBuilder()
                ->blueprintResolver(function ($table, $callback = null, $prefix = '') {
                    return new Blueprint($table, $callback, $prefix);
                });
        } catch (\Exception $e) {
            // Se a conexão ainda não estiver disponível, será configurado quando necessário
        }
    }
}

