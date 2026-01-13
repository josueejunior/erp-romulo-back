<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Tenant\Services\TenantDatabasePoolServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Comando para provisionar bancos de dados no pool
 * 
 * Executa em background (via cron) garantindo que existam sempre
 * bancos pré-criados prontos para uso.
 * 
 * Uso: php artisan tenant:provisionar-pool [--count=10]
 */
class ProvisionarPoolBancosCommand extends Command
{
    protected $signature = 'tenant:provisionar-pool 
                            {--count=10 : Quantidade de bancos a criar}
                            {--min=5 : Quantidade mínima de bancos no pool}';

    protected $description = 'Provisiona bancos de dados no pool para reduzir latência no cadastro';

    public function __construct(
        private readonly TenantDatabasePoolServiceInterface $poolService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔧 Provisionando pool de bancos de dados...');

        $count = (int) $this->option('count');
        $min = (int) $this->option('min');

        // Verificar quantos bancos já existem
        $disponiveis = $this->poolService->contarBancosDisponiveis();
        
        $this->info("📊 Bancos disponíveis no pool: {$disponiveis}");

        // Se já temos o mínimo, apenas criar os que faltam
        if ($disponiveis < $min) {
            $necessarios = $min - $disponiveis;
            $this->info("⚡ Criando {$necessarios} bancos para atingir o mínimo...");
            
            $criados = $this->poolService->provisionarBancos($necessarios);
            
            if ($criados > 0) {
                $this->info("✅ {$criados} bancos criados com sucesso!");
            } else {
                $this->warn("⚠️ Nenhum banco foi criado. Verifique os logs.");
            }
        } else {
            $this->info("✅ Pool já tem bancos suficientes ({$disponiveis} disponíveis)");
        }

        // Se foi especificado um count maior, criar bancos adicionais
        if ($count > $min) {
            $adicionais = $count - $disponiveis;
            if ($adicionais > 0) {
                $this->info("⚡ Criando {$adicionais} bancos adicionais...");
                $criados = $this->poolService->provisionarBancos($adicionais);
                
                if ($criados > 0) {
                    $this->info("✅ {$criados} bancos adicionais criados!");
                }
            }
        }

        $disponiveisFinal = $this->poolService->contarBancosDisponiveis();
        $this->info("📊 Total de bancos disponíveis: {$disponiveisFinal}");

        return Command::SUCCESS;
    }
}


