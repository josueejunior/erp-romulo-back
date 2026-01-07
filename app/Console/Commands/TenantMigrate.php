<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

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
                            {--path= : Caminho específico da migration (opcional)}';

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

        $path = $this->option('path') ?: 'database/migrations/tenant';

        foreach ($tenants as $tenant) {
            $this->info("🔄 Executando migrations no tenant: {$tenant->id} ({$tenant->razao_social})");
            
            try {
                tenancy()->initialize($tenant);
                
                $params = [
                    '--path' => $path,
                    '--force' => $this->option('force') ?: true,
                ];
                
                \Artisan::call('migrate', $params);
                
                $output = \Artisan::output();
                if (!empty(trim($output))) {
                    $this->line($output);
                }
                
                tenancy()->end();
                
                $this->info("  ✅ Tenant {$tenant->id} concluído!");
            } catch (\Exception $e) {
                tenancy()->end();
                $this->error("  ❌ Erro no tenant {$tenant->id}: " . $e->getMessage());
                $this->error("  Trace: " . $e->getTraceAsString());
            }
        }

        $this->info("\n✅ Migrations concluídas para todos os tenants!");
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

