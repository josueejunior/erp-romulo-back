<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

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
        $subdirs = [];
        
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
            // Sem path customizado, buscar recursivamente todos os subdiretórios de tenant
            $tenantPath = database_path('migrations/tenant');
            
            if (!File::exists($tenantPath)) {
                $this->error("Diretório de migrations de tenant não encontrado: {$tenantPath}");
                return 1;
            }
            
            // Buscar todos os subdiretórios que contêm migrations
            $subdirs = $this->getMigrationSubdirectories($tenantPath);
            
            if (empty($subdirs)) {
                $this->warn("⚠️  Nenhuma migration encontrada em: {$tenantPath}");
                return 1;
            }
            
            // Ordenar subdiretórios considerando dependências
            $subdirs = $this->orderMigrationPaths($subdirs, $tenantPath);
        }

        foreach ($tenants as $tenant) {
            $this->info("🔄 Executando migrations no tenant: {$tenant->id} ({$tenant->razao_social})");
            
            try {
                tenancy()->initialize($tenant);
                // Garantir que a conexão padrão seja o banco do tenant (no CLI o bootstrapper pode não trocar)
                $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
                if (config('database.default') === $centralConnectionName) {
                    $tenantDbName = $tenant->database()->getName();
                    config(['database.connections.tenant.database' => $tenantDbName]);
                    \Illuminate\Support\Facades\DB::purge('tenant');
                    config(['database.default' => 'tenant']);
                }

                if ($this->option('status')) {
                    // Mostrar status das migrations
                    if ($pathOption) {
                        \Artisan::call('migrate:status', $statusParams);
                        $output = \Artisan::output();
                        if (!empty(trim($output))) {
                            $this->line($output);
                        }
                    } else {
                        // Para status, mostrar de cada subdiretório
                        foreach ($subdirs as $subdir) {
                            \Artisan::call('migrate:status', [
                                '--path' => $subdir,
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
                        // Sem path: executar migrations de todos os subdiretórios encontrados
                        // Executar para cada subdiretório - o Laravel gerencia a tabela _migrations
                        // para evitar executar migrations já executadas
                        foreach ($subdirs as $subdir) {
                            \Artisan::call('migrate', [
                                '--path' => $subdir,
                                '--realpath' => true,
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
     * Get all subdirectories that contain migration files
     * Returns absolute paths that can be used with --realpath
     */
    protected function getMigrationSubdirectories(string $basePath): array
    {
        if (!File::exists($basePath)) {
            return [];
        }

        $subdirs = [];
        $files = File::allFiles($basePath);
        
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPath();
                // Usar caminho absoluto para funcionar com --realpath
                if (!in_array($path, $subdirs)) {
                    $subdirs[] = $path;
                }
            }
        }

        return $subdirs;
    }
    
    /**
     * Ordena paths para rodar migrations na ordem correta considerando dependências
     * Mesma lógica do TenantDatabaseService::orderMigrationPaths
     */
    protected function orderMigrationPaths(array $paths, string $basePath): array
    {
        // Definir ordem de prioridade (menor número = maior prioridade)
        $priority = [
            'permissions' => 1,
            'usuarios' => 2,
            'empresas' => 3,
            'fornecedores' => 4,
            'orgaos' => 5,
            'documentos' => 6,
            'processos' => 7,
            'contratos' => 8,
            'autorizacoes_fornecimento' => 9,
            'empenhos' => 10,
            'orcamentos' => 11,
            'notas_fiscais' => 12,
            'assinaturas' => 13,
        ];
        
        usort($paths, function ($a, $b) use ($basePath, $priority) {
            $aPriority = $this->getPathPriority($a, $basePath, $priority);
            $bPriority = $this->getPathPriority($b, $basePath, $priority);
            
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }
            
            // Se mesma prioridade, ordem alfabética
            return strcmp($a, $b);
        });
        
        return $paths;
    }
    
    /**
     * Obtém a prioridade de um path baseado no diretório
     */
    protected function getPathPriority(string $path, string $basePath, array $priority): int
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $path);
        $dirName = explode(DIRECTORY_SEPARATOR, $relativePath)[0];
        
        return $priority[$dirName] ?? 999; // Prioridade baixa para diretórios não listados
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

