<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisService;

class RedisCacheClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:clear 
                            {--tenant= : ID do tenant para limpar cache específico}
                            {--type= : Tipo de cache (dashboard, processos, saldo, relatorio, calendario, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpar cache do Redis por tenant ou tipo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!RedisService::isAvailable()) {
            $this->error('Redis não está disponível!');
            return 1;
        }

        $tenantId = $this->option('tenant');
        $type = $this->option('type') ?? 'all';

        if (!$tenantId) {
            $this->error('É necessário informar o --tenant=ID');
            return 1;
        }

        switch ($type) {
            case 'dashboard':
                RedisService::clearDashboard($tenantId);
                $this->info("Cache de dashboard do tenant {$tenantId} limpo!");
                break;
            
            case 'processos':
                RedisService::clearProcessos($tenantId);
                $this->info("Cache de processos do tenant {$tenantId} limpo!");
                break;
            
            case 'saldo':
                $this->warn('Para limpar saldo específico, use RedisService::clearSaldo($tenantId, $processoId)');
                break;
            
            case 'relatorio':
                RedisService::clearRelatorioFinanceiro($tenantId);
                $this->info("Cache de relatórios do tenant {$tenantId} limpo!");
                break;
            
            case 'calendario':
                RedisService::clearCalendario($tenantId);
                $this->info("Cache de calendário do tenant {$tenantId} limpo!");
                break;
            
            case 'all':
            default:
                RedisService::clearAllTenantCache($tenantId);
                $this->info("Todos os caches do tenant {$tenantId} foram limpos!");
                break;
        }

        // Mostrar estatísticas
        $stats = RedisService::getStats();
        if (!empty($stats)) {
            $this->line('');
            $this->info('Estatísticas do Redis:');
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Clientes Conectados', $stats['connected_clients'] ?? 'N/A'],
                    ['Memória Usada', $stats['used_memory_human'] ?? 'N/A'],
                    ['Comandos Processados', $stats['total_commands_processed'] ?? 'N/A'],
                    ['Cache Hits', $stats['keyspace_hits'] ?? 'N/A'],
                    ['Cache Misses', $stats['keyspace_misses'] ?? 'N/A'],
                ]
            );
        }

        return 0;
    }
}
