<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MigrateCentral extends Command
{
    protected $signature = 'migrate:central
                            {--force : Forçar execução sem confirmação}
                            {--path= : Caminho específico da migration (opcional)}
                            {--status : Mostrar status das migrations sem executá-las}';

    protected $description = 'Executa apenas as migrations do banco central (master). Use este comando no deploy do central; use tenants:migrate para os bancos dos tenants.';

    public function handle(): int
    {
        $pathOption = $this->option('path');
        $centralPath = database_path('migrations/central');

        if ($pathOption) {
            $path = str_starts_with($pathOption, '/')
                ? $pathOption
                : base_path($pathOption);
            $paths = [$path];
            $useRealpath = true;
        } else {
            if (!File::exists($centralPath)) {
                $this->error("Diretório de migrations central não encontrado: {$centralPath}");
                return 1;
            }
            $paths = $this->getMigrationSubdirectories($centralPath);
            if (empty($paths)) {
                $this->warn("Nenhuma migration encontrada em: {$centralPath}");
                return 0;
            }
            $paths = $this->orderPaths($paths, $centralPath);
            $useRealpath = true;
        }

        $this->info('Banco: central (conexão padrão). Executando apenas migrations de database/migrations/central/');

        if ($this->option('status')) {
            foreach ($paths as $path) {
                Artisan::call('migrate:status', array_filter([
                    '--path' => $path,
                    '--realpath' => $useRealpath ? true : null,
                ]));
                $output = Artisan::output();
                if (trim($output) !== '') {
                    $this->line($output);
                }
            }
            return 0;
        }

        $force = $this->option('force') ?: true;
        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--realpath' => $useRealpath,
                '--force' => $force,
            ]);
            $output = Artisan::output();
            if (trim($output) !== '' && trim($output) !== 'Nothing to migrate.') {
                $this->line($output);
            }
        }

        $this->info('Migrations do central concluídas.');
        return 0;
    }

    /**
     * Ordena os paths para rodar tenancy primeiro (tabelas base), depois o restante.
     */
    protected function orderPaths(array $paths, string $basePath): array
    {
        $tenancyDir = $basePath . DIRECTORY_SEPARATOR . 'tenancy';
        usort($paths, function ($a, $b) use ($tenancyDir) {
            $aIsTenancy = str_starts_with($a, $tenancyDir) || $a === $tenancyDir;
            $bIsTenancy = str_starts_with($b, $tenancyDir) || $b === $tenancyDir;
            if ($aIsTenancy && !$bIsTenancy) return -1;
            if (!$aIsTenancy && $bIsTenancy) return 1;
            return strcmp($a, $b);
        });
        return $paths;
    }

    protected function getMigrationSubdirectories(string $basePath): array
    {
        if (!File::exists($basePath)) {
            return [];
        }
        $subdirs = [];
        foreach (File::allFiles($basePath) as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPath();
                if (!in_array($path, $subdirs, true)) {
                    $subdirs[] = $path;
                }
            }
        }
        return $subdirs;
    }
}
