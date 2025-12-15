<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Contrato;
use App\Models\AutorizacaoFornecimento;
use App\Models\Empenho;
use App\Models\NotaFiscal;
use Illuminate\Support\Facades\DB;

class FinanceiroService
{
    /**
     * Calcula o saldo financeiro de um processo
     */
    public function calcularSaldoProcesso(Processo $processo): array
    {
        // Saldo de contratos
        $saldoContratos = $processo->contratos()
            ->where('situacao', 'vigente')
            ->sum('saldo');

        // Saldo de AFs
        $saldoAFs = $processo->autorizacoesFornecimento()
            ->whereIn('situacao', ['aguardando_empenho', 'atendendo'])
            ->sum('saldo');

        // Saldo de empenhos diretos (não vinculados a contrato ou AF)
        $saldoEmpenhosDiretos = $processo->empenhos()
            ->whereNull('contrato_id')
            ->whereNull('autorizacao_fornecimento_id')
            ->where('concluido', false)
            ->sum('valor');

        // Total de receita esperada
        $totalReceita = $saldoContratos + $saldoAFs + $saldoEmpenhosDiretos;

        // Custos diretos (Notas Fiscais de entrada)
        $custosDiretos = $processo->notasFiscais()
            ->where('tipo', 'entrada')
            ->sum('valor');

        // Saldo do processo
        $saldoProcesso = $totalReceita - $custosDiretos;

        return [
            'saldo_contratos' => round($saldoContratos, 2),
            'saldo_afs' => round($saldoAFs, 2),
            'saldo_empenhos_diretos' => round($saldoEmpenhosDiretos, 2),
            'total_receita' => round($totalReceita, 2),
            'custos_diretos' => round($custosDiretos, 2),
            'saldo_processo' => round($saldoProcesso, 2),
        ];
    }

    /**
     * Calcula lucro e margem de um processo
     */
    public function calcularLucroProcesso(Processo $processo): array
    {
        $saldo = $this->calcularSaldoProcesso($processo);
        
        $lucro = $saldo['saldo_processo'];
        $margem = $saldo['total_receita'] > 0 
            ? ($lucro / $saldo['total_receita']) * 100 
            : 0;

        return [
            'lucro' => round($lucro, 2),
            'margem_percentual' => round($margem, 2),
            'receita_total' => $saldo['total_receita'],
            'custo_total' => $saldo['custos_diretos'],
        ];
    }

    /**
     * Atualiza saldo de um contrato baseado nos empenhos vinculados
     */
    public function atualizarSaldoContrato(Contrato $contrato): void
    {
        $valorEmpenhado = $contrato->empenhos()
            ->sum('valor');

        $contrato->saldo = $contrato->valor_total - $valorEmpenhado;
        $contrato->save();
    }

    /**
     * Atualiza saldo de uma AF baseado nos empenhos vinculados
     */
    public function atualizarSaldoAF(AutorizacaoFornecimento $af): void
    {
        $valorEmpenhado = $af->empenhos()
            ->sum('valor');

        $af->saldo = $af->valor - $valorEmpenhado;
        
        // Atualizar situação
        if ($af->saldo <= 0) {
            $af->situacao = 'concluida';
        } elseif ($af->empenhos()->where('concluido', false)->exists()) {
            $af->situacao = 'atendendo';
        }
        
        $af->save();
    }

    /**
     * Calcula totais financeiros por período
     */
    public function calcularTotaisPorPeriodo(string $dataInicio, string $dataFim): array
    {
        $processos = Processo::whereBetween('created_at', [$dataInicio, $dataFim])
            ->with(['contratos', 'autorizacoesFornecimento', 'empenhos', 'notasFiscais'])
            ->get();

        $totalReceita = 0;
        $totalCustos = 0;
        $totalLucro = 0;

        foreach ($processos as $processo) {
            $saldo = $this->calcularSaldoProcesso($processo);
            $totalReceita += $saldo['total_receita'];
            $totalCustos += $saldo['custos_diretos'];
            $totalLucro += $saldo['saldo_processo'];
        }

        $margemMedia = $totalReceita > 0 
            ? ($totalLucro / $totalReceita) * 100 
            : 0;

        return [
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
            ],
            'total_receita' => round($totalReceita, 2),
            'total_custos' => round($totalCustos, 2),
            'total_lucro' => round($totalLucro, 2),
            'margem_media' => round($margemMedia, 2),
            'quantidade_processos' => $processos->count(),
        ];
    }

    /**
     * Calcula saldo a receber (empenhos não concluídos)
     */
    public function calcularSaldoAReceber(int $tenantId = null): float
    {
        $query = Empenho::where('concluido', false);

        // Se houver tenant, filtrar por processos do tenant
        // Como estamos usando multi-tenancy, isso é tratado automaticamente

        return round($query->sum('valor'), 2);
    }
}

