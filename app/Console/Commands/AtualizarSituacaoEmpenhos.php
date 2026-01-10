<?php

namespace App\Console\Commands;

use App\Modules\Empenho\Models\Empenho;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;

class AtualizarSituacaoEmpenhos extends Command
{
    protected $signature = 'empenhos:atualizar-situacao';
    protected $description = 'Atualiza situação de empenhos baseado em prazos e notas fiscais';

    public function handle(): int
    {
        try {
            $this->info('Iniciando atualização de situação de empenhos...');
            
            $totalAtualizados = 0;
            $totalAtrasados = 0;
            $totalConcluidos = 0;

            // Buscar todos os tenants
            $tenants = Tenant::all();

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $this->line("Processando tenant: {$tenant->id} - {$tenant->razao_social}");

                    // Buscar empenhos não concluídos
                    $empenhos = Empenho::where('concluido', false)
                        ->with(['notasFiscais'])
                        ->get();

                    foreach ($empenhos as $empenho) {
                        $situacaoAnterior = $empenho->situacao;
                        $concluidoAnterior = $empenho->concluido;
                        
                        // Atualizar situação (inclui verificação de conclusão)
                        $empenho->atualizarSaldo();
                        
                        // Verificar se houve mudança
                        if ($empenho->situacao !== $situacaoAnterior || $empenho->concluido !== $concluidoAnterior) {
                            $totalAtualizados++;
                            
                            if ($empenho->situacao === 'atrasado' && $situacaoAnterior !== 'atrasado') {
                                $totalAtrasados++;
                            }
                            
                            if ($empenho->concluido && !$concluidoAnterior) {
                                $totalConcluidos++;
                            }
                            
                            Log::info('Situação de empenho atualizada', [
                                'empenho_id' => $empenho->id,
                                'processo_id' => $empenho->processo_id,
                                'situacao_anterior' => $situacaoAnterior,
                                'situacao_nova' => $empenho->situacao,
                                'concluido' => $empenho->concluido,
                            ]);
                        }
                    }

                    tenancy()->end();
                } catch (\Exception $e) {
                    Log::error('Erro ao processar tenant no comando de atualização de situação de empenhos', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("  Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }

            $this->info("Total de empenhos atualizados: {$totalAtualizados}");
            $this->info("Total de empenhos marcados como atrasados: {$totalAtrasados}");
            $this->info("Total de empenhos concluídos: {$totalConcluidos}");
            $this->info('Atualização de situação de empenhos concluída!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('empenhos:atualizar-situacao erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

