<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Models\Processo;

class VerificarValoresProcessoItem extends Command
{
    protected $signature = 'processo:verificar-valores {processo_id}';
    protected $description = 'Verifica os valores financeiros dos itens de um processo';

    public function handle()
    {
        $processoId = $this->argument('processo_id');
        
        $processo = Processo::find($processoId);
        if (!$processo) {
            $this->error("Processo {$processoId} não encontrado");
            return 1;
        }
        
        $this->info("Processo: {$processo->numero_modalidade} (ID: {$processo->id})");
        $this->info("Status: {$processo->status}");
        $this->line('');
        
        $itens = $processo->itens()
            ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
            ->get();
        
        if ($itens->isEmpty()) {
            $this->warn("Nenhum item aceito encontrado");
            return 0;
        }
        
        $this->info("Itens aceitos: {$itens->count()}");
        $this->line('');
        
        $headers = ['Item', 'Qtd', 'Valor Arrematado', 'Valor Negociado', 'Valor Final Sessão', 'Valor Estimado', 'Valor Vencido', 'Saldo Aberto'];
        $rows = [];
        
        foreach ($itens as $item) {
            $rows[] = [
                $item->numero_item ?? $item->id,
                $item->quantidade ?? 0,
                $item->valor_arrematado ? 'R$ ' . number_format($item->valor_arrematado, 2, ',', '.') : '-',
                $item->valor_negociado ? 'R$ ' . number_format($item->valor_negociado, 2, ',', '.') : '-',
                $item->valor_final_sessao ? 'R$ ' . number_format($item->valor_final_sessao, 2, ',', '.') : '-',
                $item->valor_estimado ? 'R$ ' . number_format($item->valor_estimado, 2, ',', '.') : '-',
                $item->valor_vencido ? 'R$ ' . number_format($item->valor_vencido, 2, ',', '.') : '-',
                $item->saldo_aberto ? 'R$ ' . number_format($item->saldo_aberto, 2, ',', '.') : '-',
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->line('');
        $this->info("Recalculando valores...");
        
        foreach ($itens as $item) {
            $item->atualizarValoresFinanceiros();
        }
        
        $this->info("Valores recalculados! Verifique os logs para detalhes.");
        
        return 0;
    }
}

