<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

class TenantMigrateRollback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate-rollback 
                            {--tenants=* : IDs dos tenants específicos (opcional)}
                            {--force : Forçar execução sem confirmação}
                            {--step= : Número de migrations para fazer rollback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Faz rollback das migrations dos tenants';

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
            $step = $this->option('step') ? " {$this->option('step')} migration(s)" : ' todas as migrations';
            if (!$this->confirm("⚠️  ATENÇÃO: Isso irá fazer ROLLBACK de{$step}. Isso pode causar PERDA DE DADOS! Deseja continuar?")) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }

        foreach ($tenants as $tenant) {
            $this->info("🔄 Processando tenant: {$tenant->id}");
            
            try {
                tenancy()->initialize($tenant);
                
                $params = [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ];
                
                if ($this->option('step')) {
                    $params['--step'] = $this->option('step');
                }
                
                \Artisan::call('migrate:rollback', $params);
                
                tenancy()->end();
                
                $this->info("  ✅ Tenant {$tenant->id} concluído!");
            } catch (\Exception $e) {
                tenancy()->end();
                $this->error("  ❌ Erro no tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("\n✅ Rollback concluído para todos os tenants!");
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

