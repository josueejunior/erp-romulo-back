<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider para configurações de Schema
 * O Blueprint customizado é usado diretamente nas migrations através do import
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
        // O Blueprint customizado será usado quando importado nas migrations:
        // use App\Database\Schema\Blueprint;
    }
}

