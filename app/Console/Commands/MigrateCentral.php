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
                            {--status : Mostrar status das migrations sem executá-las}
                            {--seed : Executar seeds após as migrations}';

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
            
            // 🔥 GARANTIR: Buscar todas as migrations (raiz + subdiretórios)
            $paths = $this->getAllMigrationPaths($centralPath);
            
            if (empty($paths)) {
                $this->warn("Nenhuma migration encontrada em: {$centralPath}");
                return 0;
            }
            
            // Ordenar paths para garantir ordem correta de execução
            $paths = $this->orderPaths($paths, $centralPath);
            $useRealpath = true;
        }

        $this->info('Banco: central (conexão padrão). Executando migrations de database/migrations/central/');
        $this->info('Total de diretórios/paths encontrados: ' . count($paths));

        if ($this->option('status')) {
            foreach ($paths as $path) {
                $this->line("Verificando: {$path}");
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
        $executed = 0;
        $skipped = 0;
        
        foreach ($paths as $path) {
            $this->line("Executando migrations em: {$path}");
            Artisan::call('migrate', [
                '--path' => $path,
                '--realpath' => $useRealpath,
                '--force' => $force,
            ]);
            $output = Artisan::output();
            if (trim($output) !== '' && trim($output) !== 'Nothing to migrate.') {
                $this->line($output);
                $executed++;
            } else {
                $skipped++;
            }
        }

        $this->info("✅ Migrations do central concluídas. Executadas: {$executed}, Já executadas: {$skipped}");
        
        // Executar seeds se solicitado
        if ($this->option('seed')) {
            $this->info('');
            $this->info('🌱 Executando seeds do banco central...');
            try {
                Artisan::call('db:seed', [
                    '--force' => $force,
                    '--class' => 'DatabaseSeeder',
                ]);
                $output = Artisan::output();
                if (trim($output) !== '') {
                    $this->line($output);
                }
                $this->info('✅ Seeds do central concluídos.');
            } catch (\Exception $e) {
                $this->warn("⚠️  Erro ao executar seeds: {$e->getMessage()}");
                // Não falhar o comando se seeds derem erro
            }
        }
        
        return 0;
    }

    /**
     * 🔥 GARANTIR: Busca todas as migrations (raiz + subdiretórios)
     * Retorna array com todos os diretórios que contêm migrations
     */
    protected function getAllMigrationPaths(string $basePath): array
    {
        if (!File::exists($basePath)) {
            return [];
        }
        
        $paths = [];
        
        // 1. Verificar se há migrations na raiz
        $rootFiles = File::files($basePath);
        $hasRootMigrations = false;
        foreach ($rootFiles as $file) {
            if ($file->getExtension() === 'php') {
                $hasRootMigrations = true;
                break;
            }
        }
        
        if ($hasRootMigrations) {
            $paths[] = $basePath; // Adicionar raiz
        }
        
        // 2. Buscar migrations em subdiretórios recursivamente
        foreach (File::allFiles($basePath) as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPath();
                // Não adicionar a raiz novamente (já foi adicionada acima)
                if ($path !== $basePath && !in_array($path, $paths, true)) {
                    $paths[] = $path;
                }
            }
        }
        
        return $paths;
    }

    /**
     * Ordena os paths para rodar na ordem correta:
     * 1. tenancy (tabelas base)
     * 2. raiz (migrations diretas)
     * 3. subdiretórios (ordenados alfabeticamente)
     */
    protected function orderPaths(array $paths, string $basePath): array
    {
        $tenancyDir = $basePath . DIRECTORY_SEPARATOR . 'tenancy';
        
        usort($paths, function ($a, $b) use ($tenancyDir, $basePath) {
            // Prioridade 1: tenancy primeiro
            $aIsTenancy = str_starts_with($a, $tenancyDir) || $a === $tenancyDir;
            $bIsTenancy = str_starts_with($b, $tenancyDir) || $b === $tenancyDir;
            if ($aIsTenancy && !$bIsTenancy) return -1;
            if (!$aIsTenancy && $bIsTenancy) return 1;
            
            // Prioridade 2: raiz depois de tenancy
            $aIsRoot = $a === $basePath;
            $bIsRoot = $b === $basePath;
            if ($aIsRoot && !$bIsRoot && !$bIsTenancy) return -1;
            if (!$aIsRoot && $bIsRoot && !$aIsTenancy) return 1;
            
            // Prioridade 3: ordem alfabética para o restante
            return strcmp($a, $b);
        });
        
        return $paths;
    }
}
