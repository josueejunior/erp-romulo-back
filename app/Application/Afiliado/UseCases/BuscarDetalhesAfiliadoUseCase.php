<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use App\Modules\Afiliado\Models\AfiliadoIndicacao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use DomainException;

/**
 * Use Case para buscar detalhes do Afiliado com estatísticas
 */
final class BuscarDetalhesAfiliadoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(
        int $id,
        ?string $status = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        int $perPage = 15,
        int $page = 1,
    ): array {
        Log::debug('BuscarDetalhesAfiliadoUseCase::executar', [
            'id' => $id,
            'status' => $status,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ]);

        // Buscar afiliado
        $afiliado = Afiliado::withCount([
            'indicacoes',
            'indicacoesAtivas',
            'indicacoesInadimplentes',
            'indicacoesCanceladas',
        ])->find($id);

        if (!$afiliado) {
            throw new DomainException('Afiliado não encontrado.');
        }

        // Buscar indicações com filtros
        $query = AfiliadoIndicacao::where('afiliado_id', $id)
            ->orderBy('indicado_em', 'desc');

        if ($status) {
            $query->porStatus($status);
        }

        if ($dataInicio || $dataFim) {
            $query->porPeriodo($dataInicio, $dataFim);
        }

        $indicacoes = $query->paginate($perPage, ['*'], 'page', $page);

        // Calcular estatísticas
        $estatisticas = $this->calcularEstatisticas($id);

        return [
            'afiliado' => $afiliado,
            'indicacoes' => $indicacoes,
            'estatisticas' => $estatisticas,
        ];
    }

    /**
     * Calcula estatísticas do afiliado
     */
    private function calcularEstatisticas(int $afiliadoId): array
    {
        $indicacoes = AfiliadoIndicacao::where('afiliado_id', $afiliadoId);

        // Total de indicações por status
        $porStatus = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Valores de comissão
        // 🔥 CORREÇÃO: Converter para float pois sum() pode retornar string no PostgreSQL
        $totalComissoes = (float) (AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->whereNotNull('primeira_assinatura_em')
            ->sum('valor_comissao') ?? 0);

        $comissoesPagas = (float) (AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->where('comissao_paga', true)
            ->sum('valor_comissao') ?? 0);

        $comissoesPendentes = $totalComissoes - $comissoesPagas;

        // Tempo médio de retenção (apenas ativos)
        $tempoMedioRetencao = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->where('status', 'ativa')
            ->whereNotNull('indicado_em')
            ->avg(DB::raw('EXTRACT(DAY FROM (NOW() - indicado_em))'));

        // Taxa de conversão (trial para ativa)
        $totalTrials = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->whereNotNull('indicado_em')
            ->count();

        $convertidos = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->whereNotNull('primeira_assinatura_em')
            ->count();

        $taxaConversao = $totalTrials > 0 ? ($convertidos / $totalTrials) * 100 : 0;

        // Evolução mensal (últimos 6 meses)
        $evolucaoMensal = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
            ->where('indicado_em', '>=', now()->subMonths(6))
            ->select(
                DB::raw("TO_CHAR(indicado_em, 'YYYY-MM') as mes"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return [
            'por_status' => [
                'ativas' => $porStatus['ativa'] ?? 0,
                'inadimplentes' => $porStatus['inadimplente'] ?? 0,
                'canceladas' => $porStatus['cancelada'] ?? 0,
                'trial' => $porStatus['trial'] ?? 0,
            ],
            'comissoes' => [
                'total' => round((float) $totalComissoes, 2),
                'pagas' => round((float) $comissoesPagas, 2),
                'pendentes' => round((float) $comissoesPendentes, 2),
            ],
            'retencao' => [
                'tempo_medio_dias' => round((float) ($tempoMedioRetencao ?? 0)),
            ],
            'conversao' => [
                'total_trials' => $totalTrials,
                'convertidos' => $convertidos,
                'taxa_percentual' => round((float) $taxaConversao, 1),
            ],
            'evolucao_mensal' => $evolucaoMensal,
        ];
    }
}









