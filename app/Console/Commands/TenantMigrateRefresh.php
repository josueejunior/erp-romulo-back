<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

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
                
                $this->info("  ↳ Fazendo rollback...");
                \Artisan::call('migrate:rollback', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
                
                $this->info("  ↳ Executando migrations...");
                \Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
                
                if ($this->option('seed')) {
                    $this->info("  ↳ Executando seeds...");
                    \Artisan::call('db:seed', [
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

