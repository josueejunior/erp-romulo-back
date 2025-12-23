<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * Service Provider para carregar migrations recursivamente
 * Organiza migrations por módulos/contextos e ordena por timestamp
 */
class DatabaseServiceProvider extends ServiceProvider
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
     * Carrega todas as migrations recursivamente e ordena por timestamp
     */
    public function boot(): void
    {
        // Carregar migrations recursivamente e ordenar por timestamp
        // para manter a ordem correta de execução entre níveis/pastas
        $paths = collect(File::allFiles(database_path('migrations')))
            ->filter(static fn (SplFileInfo $info) => $info->getExtension() === 'php')
            ->sortBy(static fn(SplFileInfo $info) => $info->getFilename())
            ->map(static fn(SplFileInfo $info) => $info->getPath())
            ->unique()
            ->all();

        // Carregar migrations de cada diretório encontrado
        foreach ($paths as $path) {
            $this->loadMigrationsFrom($path);
        }

        // Também carregar migrations da raiz (compatibilidade)
        $this->loadMigrationsFrom(database_path('migrations'));
    }
}

