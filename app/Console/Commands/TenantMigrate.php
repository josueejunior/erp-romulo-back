<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class TenantMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate 
                            {--tenants=* : IDs dos tenants específicos (opcional)}
                            {--force : Forçar execução sem confirmação}
                            {--path= : Caminho específico da migration (opcional)}
                            {--status : Mostrar status das migrations sem executá-las}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa migrations nos tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenants = $this->getTenants();
        
        if (empty($tenants)) {
            $this->error('Nenhum tenant encontrado.');
            return 1;
        }

        $pathOption = $this->option('path');
        
        // Preparar parâmetros de migração
        $migrateParams = [
            '--force' => $this->option('force') ?: true,
        ];
        
        $statusParams = [];
        $migrationFiles = [];
        
        if ($pathOption) {
            // Se um path customizado foi fornecido, usar ele
            $path = str_starts_with($pathOption, '/') 
                ? $pathOption 
                : base_path($pathOption);
            $migrateParams['--path'] = $path;
            $migrateParams['--realpath'] = true;
            $statusParams['--path'] = $path;
            $statusParams['--realpath'] = true;
        } else {
            // Sem path customizado, buscar recursivamente todos os arquivos de migration de tenant
            $tenantPath = database_path('migrations/tenant');
            
            if (!File::exists($tenantPath)) {
                $this->error("Diretório de migrations de tenant não encontrado: {$tenantPath}");
                return 1;
            }
            
            // Buscar todos os arquivos de migration em ordem global (timestamp)
            $migrationFiles = $this->getMigrationFilesOrdered($tenantPath);

            if (empty($migrationFiles)) {
                $this->warn("⚠️  Nenhuma migration encontrada em: {$tenantPath}");
                return 1;
            }
        }

        foreach ($tenants as $tenant) {
            $this->info("🔄 Executando migrations no tenant: {$tenant->id} ({$tenant->razao_social})");
            
            try {
                tenancy()->initialize($tenant);
                
                if ($this->option('status')) {
                    // Mostrar status das migrations
                    if ($pathOption) {
                        \Artisan::call('migrate:status', $statusParams);
                        $output = \Artisan::output();
                        if (!empty(trim($output))) {
                            $this->line($output);
                        }
                    } else {
                        // Para status, mostrar de cada arquivo de migration
                        foreach ($migrationFiles as $migrationFile) {
                            \Artisan::call('migrate:status', [
                                '--path' => $migrationFile,
                                '--realpath' => true,
                            ]);
                            $output = \Artisan::output();
                            if (!empty(trim($output))) {
                                $this->line($output);
                            }
                        }
                    }
                } else {
                    // Executar migrations
                    if ($pathOption) {
                        // Path customizado: usar diretamente
                        \Artisan::call('migrate', $migrateParams);
                        $output = \Artisan::output();
                        if (!empty(trim($output))) {
                            $this->line($output);
                        }
                    } else {
                        // Sem path: executar migrations em ordem global de timestamp
                        foreach ($migrationFiles as $migrationFile) {
                            \Artisan::call('migrate', [
                                '--path' => $migrationFile,
                                '--realpath' => true,
                                '--database' => 'tenant',
                                '--force' => $this->option('force') ?: true,
                            ]);
                            $output = \Artisan::output();
                            if (!empty(trim($output)) && trim($output) !== 'Nothing to migrate.') {
                                $this->line($output);
                            }
                        }
                    }
                }
                
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                
                if (!$this->option('status')) {
                    $this->info("  ✅ Tenant {$tenant->id} concluído!");
                }
            } catch (\Exception $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                $this->error("  ❌ Erro no tenant {$tenant->id}: " . $e->getMessage());
                if ($this->option('verbose')) {
                    $this->error("  Trace: " . $e->getTraceAsString());
                }
            }
        }

        $this->info("\n✅ Migrations concluídas para todos os tenants!");
        return 0;
    }

    /**
     * Retorna arquivos de migration em ordem global (timestamp do nome).
     */
    protected function getMigrationFilesOrdered(string $basePath): array
    {
        if (!File::exists($basePath)) {
            return [];
        }

        $files = array_filter(File::allFiles($basePath), function (SplFileInfo $file) {
            return $file->getExtension() === 'php';
        });

        usort($files, function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getFilename(), $b->getFilename());
        });

        return array_map(function (SplFileInfo $file) {
            return $file->getPathname();
        }, $files);
    }

    /**
     * Get tenants to process
     */
    protected function getTenants()
    {
        $tenantIds = $this->option('tenants');
        
        if (!empty($tenantIds)) {
            return Tenant::whereIn('id', $tenantIds)->get();
        }
        
        return Tenant::all();
    }
}

