<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use App\Models\AfiliadoComissaoRecorrente;
use App\Models\AfiliadoPagamentoComissao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Use Case: Buscar Comissões de Afiliado
 * 
 * Busca comissões recorrentes e pagamentos para um afiliado
 */
final class BuscarComissoesAfiliadoUseCase
{
    /**
     * Busca comissões do afiliado
     */
    public function executar(
        int $afiliadoId,
        ?string $status = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        int $perPage = 15,
        int $page = 1,
    ): array {
        Log::debug('BuscarComissoesAfiliadoUseCase::executar', [
            'afiliado_id' => $afiliadoId,
            'status' => $status,
        ]);

        // Verificar se afiliado existe
        $afiliado = Afiliado::find($afiliadoId);
        if (!$afiliado) {
            throw new \DomainException('Afiliado não encontrado.');
        }

        // Buscar comissões recorrentes
        $query = AfiliadoComissaoRecorrente::where('afiliado_id', $afiliadoId)
            ->orderBy('data_inicio_ciclo', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($dataInicio) {
            $query->where('data_inicio_ciclo', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_inicio_ciclo', '<=', $dataFim);
        }

        $comissoes = $query->paginate($perPage, ['*'], 'page', $page);

        // Buscar pagamentos
        $pagamentos = AfiliadoPagamentoComissao::where('afiliado_id', $afiliadoId)
            ->orderBy('periodo_competencia', 'desc')
            ->limit(10)
            ->get();

        return [
            'comissoes' => $comissoes,
            'pagamentos' => $pagamentos,
        ];
    }

    /**
     * Busca resumo de comissões (estatísticas)
     */
    public function buscarResumo(int $afiliadoId): array
    {
        // Total de comissões pendentes
        $comissoesPendentes = AfiliadoComissaoRecorrente::where('afiliado_id', $afiliadoId)
            ->where('status', 'pendente')
            ->sum('valor_comissao');

        // Total de comissões pagas
        $comissoesPagas = AfiliadoComissaoRecorrente::where('afiliado_id', $afiliadoId)
            ->where('status', 'paga')
            ->sum('valor_comissao');

        // Total de pagamentos recebidos
        $totalPagamentos = AfiliadoPagamentoComissao::where('afiliado_id', $afiliadoId)
            ->where('status', 'pago')
            ->sum('valor_total');

        // Comissões do mês atual
        $mesAtual = Carbon::now()->startOfMonth();
        $comissoesMesAtual = AfiliadoComissaoRecorrente::where('afiliado_id', $afiliadoId)
            ->where('data_inicio_ciclo', '>=', $mesAtual)
            ->where('status', 'pendente')
            ->sum('valor_comissao');

        // Quantidade de clientes ativos
        $clientesAtivos = \App\Modules\Afiliado\Models\AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->where('status', 'ativa')
            ->count();

        return [
            'comissoes_pendentes' => round($comissoesPendentes, 2),
            'comissoes_pagas' => round($comissoesPagas, 2),
            'total_pagamentos_recebidos' => round($totalPagamentos, 2),
            'comissoes_mes_atual' => round($comissoesMesAtual, 2),
            'clientes_ativos' => $clientesAtivos,
        ];
    }
}

