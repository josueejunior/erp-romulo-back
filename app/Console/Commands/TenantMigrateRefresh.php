<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class TenantMigrateRefresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate-refresh 
                            {--tenants=* : IDs dos tenants específicos (opcional)}
                            {--force : Forçar execução sem confirmação}
                            {--seed : Executar seeds após refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Faz refresh das migrations dos tenants (rollback + migrate)';

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

        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  ATENÇÃO: Isso irá fazer ROLLBACK de todas as migrations e executá-las novamente. Isso pode causar PERDA DE DADOS! Deseja continuar?')) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }

        foreach ($tenants as $tenant) {
            $this->info("🔄 Processando tenant: {$tenant->id}");
            
            try {
                tenancy()->initialize($tenant);

                $tenantPath = database_path('migrations/tenant');
                $migrationFiles = $this->getMigrationFilesOrdered($tenantPath);

                if (empty($migrationFiles)) {
                    throw new \RuntimeException("Nenhuma migration de tenant encontrada em {$tenantPath}");
                }

                $this->info("  ↳ Limpando banco do tenant (db:wipe)...");
                \Artisan::call('db:wipe', [
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                $this->info("  ↳ Executando migrations em ordem global...");
                foreach ($migrationFiles as $migrationFile) {
                    \Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => $migrationFile,
                        '--realpath' => true,
                        '--force' => true,
                    ]);
                    $output = \Artisan::output();
                    if (!empty(trim($output)) && trim($output) !== 'Nothing to migrate.') {
                        $this->line($output);
                    }
                }
                
                if ($this->option('seed')) {
                    $this->info("  ↳ Executando seeds...");
                    \Artisan::call('db:seed', [
                        '--database' => 'tenant',
                        '--force' => true,
                    ]);
                }
                
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                
                $this->info("  ✅ Tenant {$tenant->id} concluído!");
            } catch (\Exception $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                $this->error("  ❌ Erro no tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("\n✅ Refresh concluído para todos os tenants!");
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

