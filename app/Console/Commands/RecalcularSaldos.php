<?php

namespace App\Console\Commands;

use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\Processo\Models\Processo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;

class RecalcularSaldos extends Command
{
    protected $signature = 'saldos:recalcular';
    protected $description = 'Recalcula saldos de processos, contratos, AFs e empenhos (fallback)';

    public function handle(): int
    {
        try {
            $this->info('Iniciando recálculo de saldos...');
            
            $totalContratos = 0;
            $totalAFs = 0;
            $totalEmpenhos = 0;
            $totalProcessos = 0;

            // Buscar todos os tenants
            $tenants = Tenant::all();

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $this->line("Processando tenant: {$tenant->id} - {$tenant->razao_social}");

                    // Recalcular contratos ativos
                    $contratos = Contrato::where('vigente', true)->get();
                    foreach ($contratos as $contrato) {
                        $contrato->atualizarSaldo();
                        $totalContratos++;
                    }

                    // Recalcular AFs ativas
                    $afs = AutorizacaoFornecimento::where('vigente', true)->get();
                    foreach ($afs as $af) {
                        $af->atualizarSaldo();
                        $totalAFs++;
                    }

                    // Recalcular empenhos não concluídos
                    $empenhos = Empenho::where('concluido', false)->get();
                    foreach ($empenhos as $empenho) {
                        $empenho->atualizarSaldo();
                        $totalEmpenhos++;
                    }

                    // Processos em execução (saldos são calculados dinamicamente, mas podemos forçar recálculo se necessário)
                    // Por enquanto, apenas logamos
                    $processosExecucao = Processo::where('status', 'execucao')->count();
                    $totalProcessos += $processosExecucao;

                    tenancy()->end();
                } catch (\Exception $e) {
                    Log::error('Erro ao processar tenant no comando de recálculo de saldos', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("  Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
                    if (tenancy()->initialized()) {
                        tenancy()->end();
                    }
                }
            }

            $this->info("Total de contratos recalculados: {$totalContratos}");
            $this->info("Total de AFs recalculadas: {$totalAFs}");
            $this->info("Total de empenhos recalculados: {$totalEmpenhos}");
            $this->info("Total de processos em execução: {$totalProcessos}");
            $this->info('Recálculo de saldos concluído!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('saldos:recalcular erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

