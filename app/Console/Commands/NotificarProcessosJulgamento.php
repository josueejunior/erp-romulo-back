<?php

namespace App\Console\Commands;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Tenant;

class NotificarProcessosJulgamento extends Command
{
    protected $signature = 'processos:notificar-julgamento';
    protected $description = 'Notifica processos em julgamento que precisam atenção (lembretes próximos ou sem atualização há mais de 7 dias)';

    public function handle(): int
    {
        try {
            $this->info('Iniciando notificação de processos em julgamento...');
            
            $totalProcessosComLembrete = 0;
            $totalProcessosParados = 0;
            $hoje = Carbon::now();
            $em3Dias = $hoje->copy()->addDays(3);
            $dataLimiteParado = $hoje->copy()->subDays(7);

            // Buscar todos os tenants
            $tenants = Tenant::all();

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $this->line("Processando tenant: {$tenant->id} - {$tenant->razao_social}");

                    // Buscar processos em julgamento
                    $processos = Processo::where('status', 'julgamento_habilitacao')
                        ->with(['itens', 'empresa'])
                        ->get();

                    foreach ($processos as $processo) {
                        $temLembreteProximo = false;
                        $estaParado = false;

                        // Verificar lembretes dos itens (se houver campo lembrete)
                        // Por enquanto, verificamos apenas processos parados
                        
                        // Verificar se processo está parado (sem atualização há mais de 7 dias)
                        $diasSemAtualizacao = $hoje->diffInDays($processo->updated_at);
                        if ($diasSemAtualizacao > 7) {
                            $estaParado = true;
                            $totalProcessosParados++;
                            
                            Log::info('Processo em julgamento parado detectado', [
                                'processo_id' => $processo->id,
                                'empresa_id' => $processo->empresa_id,
                                'numero_modalidade' => $processo->numero_modalidade,
                                'dias_sem_atualizacao' => $diasSemAtualizacao,
                                'ultima_atualizacao' => $processo->updated_at->format('Y-m-d H:i:s'),
                            ]);
                        }

                        if ($temLembreteProximo || $estaParado) {
                            // Quando tiver sistema de usuários por empresa, criar notificações aqui
                            $totalProcessosComLembrete++;
                        }
                    }

                    tenancy()->end();
                } catch (\Exception $e) {
                    Log::error('Erro ao processar tenant no comando de notificação de julgamento', [
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

            $this->info("Total de processos com lembretes próximos: {$totalProcessosComLembrete}");
            $this->info("Total de processos parados: {$totalProcessosParados}");
            $this->info('Notificação de julgamento concluída!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('processos:notificar-julgamento erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

