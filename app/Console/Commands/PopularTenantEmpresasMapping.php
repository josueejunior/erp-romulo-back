<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando para popular mapeamento direto empresa → tenant
 * 
 * 🔥 PERFORMANCE: Este comando popula a tabela tenant_empresas
 * para eliminar loops de busca e melhorar performance.
 * 
 * Execute após criar a migration: php artisan tenant-empresas:popular
 */
class PopularTenantEmpresasMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant-empresas:popular 
                            {--force : Forçar recriação de mapeamentos existentes}
                            {--tenant-id= : Popular apenas um tenant específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Popular mapeamento direto empresa → tenant (tenant_empresas) para performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando popularização do mapeamento tenant_empresas...');
        $this->newLine();
        
        $force = $this->option('force');
        $tenantIdFilter = $this->option('tenant-id');
        
        // Buscar tenants
        $tenants = $tenantIdFilter 
            ? Tenant::where('id', $tenantIdFilter)->get()
            : Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return 0;
        }
        
        $this->info("Encontrados {$tenants->count()} tenant(s) para processar.");
        $this->newLine();
        
        $totalMapeamentos = 0;
        $totalErros = 0;
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();
        
        foreach ($tenants as $tenant) {
            try {
                $mapeamentos = $this->processarTenant($tenant, $force);
                $totalMapeamentos += $mapeamentos;
            } catch (\Exception $e) {
                $totalErros++;
                Log::error('Erro ao processar tenant no mapeamento', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $this->newLine();
                $this->error("Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Resumo
        $this->info("✅ Concluído!");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Tenants processados', $tenants->count()],
                ['Mapeamentos criados/atualizados', $totalMapeamentos],
                ['Erros', $totalErros],
            ]
        );
        
        if ($totalErros > 0) {
            $this->warn("⚠️  {$totalErros} erro(s) ocorreram. Verifique os logs para detalhes.");
        }
        
        return 0;
    }
    
    /**
     * Processar um tenant e criar mapeamentos
     */
    private function processarTenant(Tenant $tenant, bool $force): int
    {
        $mapeamentos = 0;
        
        try {
            // Inicializar tenancy
            tenancy()->initialize($tenant);
            
            try {
                // Buscar todas as empresas deste tenant
                $empresas = \App\Models\Empresa::all(['id']);
                
                foreach ($empresas as $empresa) {
                    // Verificar se mapeamento já existe
                    $existe = TenantEmpresa::where('empresa_id', $empresa->id)
                        ->where('tenant_id', $tenant->id)
                        ->exists();
                    
                    if ($existe && !$force) {
                        continue; // Já existe e não forçar
                    }
                    
                    // Criar ou atualizar mapeamento
                    TenantEmpresa::createOrUpdateMapping($tenant->id, $empresa->id);
                    $mapeamentos++;
                }
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } catch (\Exception $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            throw $e;
        }
        
        return $mapeamentos;
    }
}
