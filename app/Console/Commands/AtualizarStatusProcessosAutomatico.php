<?php

namespace App\Console\Commands;

use App\Modules\Processo\Models\Processo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AtualizarStatusProcessosAutomatico extends Command
{
    protected $signature = 'processos:atualizar-status-automatico';
    protected $description = 'Atualiza automaticamente status de processos baseado em datas (ex: participacao -> julgamento_habilitacao após sessão)';

    public function handle(): int
    {
        try {
            $agora = Carbon::now();
            
            // Processos em participação cuja sessão pública já passou
            $processosParaMudar = Processo::query()
                ->where('status', 'participacao')
                ->whereNotNull('data_hora_sessao_publica')
                ->where('data_hora_sessao_publica', '<=', $agora)
                ->get();

            foreach ($processosParaMudar as $processo) {
                $processo->status = 'julgamento_habilitacao';
                $processo->save();

                Log::info('processo:status-atualizado-automatico', [
                    'processo_id' => $processo->id,
                    'novo_status' => 'julgamento_habilitacao',
                    'motivo' => 'Sessão pública passou',
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'data_atualizacao' => $agora,
                ]);

                $this->line("Processo #{$processo->id} movido para Julgamento e Habilitação");
            }

            $count = $processosParaMudar->count();
            $this->info("Total de processos atualizados: {$count}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('processos:atualizar-status-automatico erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
