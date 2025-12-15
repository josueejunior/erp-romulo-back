<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class CleanupTempTables extends Command
{
    protected $signature = 'tenants:cleanup-temp-tables {tenant?}';
    protected $description = 'Limpa tabelas temporárias dos tenants';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        
        if ($tenantId) {
            $tenants = [\App\Models\Tenant::find($tenantId)];
        } else {
            $tenants = \App\Models\Tenant::all();
        }

        foreach ($tenants as $tenant) {
            $this->info("Processando tenant: {$tenant->id}");
            
            try {
                Tenancy::initialize($tenant);
                
                // Limpar tabelas temporárias
                DB::statement('PRAGMA foreign_keys=OFF;');
                DB::statement('DROP TABLE IF EXISTS __temp__processos');
                DB::statement('DROP TABLE IF EXISTS processos_new');
                DB::statement('PRAGMA foreign_keys=ON;');
                
                $this->info("  ✓ Tabelas temporárias removidas");
            } catch (\Exception $e) {
                $this->error("  ✗ Erro: " . $e->getMessage());
            } finally {
                Tenancy::end();
            }
        }
    }
}
