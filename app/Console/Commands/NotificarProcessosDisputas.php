<?php

namespace App\Console\Commands;

use App\Modules\Processo\Models\Processo;
use App\Modules\Orcamento\Models\Notificacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Tenant;

class NotificarProcessosDisputas extends Command
{
    protected $signature = 'processos:notificar-disputas';
    protected $description = 'Notifica processos com sessão pública próxima (próximos 3 dias)';

    public function handle(): int
    {
        try {
            $this->info('Iniciando notificação de processos com disputas próximas...');
            
            $totalNotificacoes = 0;
            $hoje = Carbon::now();
            $em3Dias = $hoje->copy()->addDays(3);

            // Buscar todos os tenants
            $tenants = Tenant::all();

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $this->line("Processando tenant: {$tenant->id} - {$tenant->razao_social}");

                    // Buscar processos com sessão pública nos próximos 3 dias
                    $processos = Processo::whereIn('status', ['participacao', 'julgamento_habilitacao'])
                        ->whereNotNull('data_hora_sessao_publica')
                        ->whereBetween('data_hora_sessao_publica', [$hoje, $em3Dias])
                        ->with(['empresa'])
                        ->get();

                    foreach ($processos as $processo) {
                        if (!$processo->data_hora_sessao_publica) {
                            continue;
                        }

                        $dataSessao = Carbon::parse($processo->data_hora_sessao_publica);
                        $diasRestantes = $hoje->diffInDays($dataSessao, false);
                        
                        if ($diasRestantes < 0) {
                            continue; // Já passou
                        }

                        // Buscar usuários da empresa para notificar
                        $empresa = $processo->empresa;
                        if (!$empresa) {
                            continue;
                        }

                        // Por enquanto, apenas logar (quando tiver sistema de usuários por empresa, criar notificações)
                        Log::info('Processo com sessão pública próxima detectado', [
                            'processo_id' => $processo->id,
                            'empresa_id' => $processo->empresa_id,
                            'numero_modalidade' => $processo->numero_modalidade,
                            'data_sessao' => $dataSessao->format('Y-m-d H:i'),
                            'dias_restantes' => $diasRestantes,
                        ]);

                        $totalNotificacoes++;
                    }

                    tenancy()->end();
                } catch (\Exception $e) {
                    Log::error('Erro ao processar tenant no comando de notificação de disputas', [
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

            $this->info("Total de processos detectados: {$totalNotificacoes}");
            $this->info('Notificação de disputas concluída!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('processos:notificar-disputas erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

