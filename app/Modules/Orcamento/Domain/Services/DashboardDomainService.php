<?php

namespace App\Modules\Orcamento\Domain\Services;

use App\Modules\Orcamento\Domain\Repositories\DashboardRepositoryInterface;

class DashboardDomainService
{
    private DashboardRepositoryInterface $repository;

    public function __construct(DashboardRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obter dashboard completo com todas as métricas
     */
    public function obterDashboardCompleto(int $empresaId): array
    {
        return [
            'metricas' => $this->obterMetricas($empresaId),
            'status' => $this->obterResumoStatus($empresaId),
            'analise_precos' => $this->obterAnalisePrecos($empresaId),
            'performance_fornecedores' => $this->obterPerformanceFornecedores($empresaId),
            'timeline' => $this->obterTimeline($empresaId),
            'processos_maior_gasto' => $this->obterProcessosMaiorGasto($empresaId)
        ];
    }

    /**
     * Obter métricas com cálculos de negócio
     */
    public function obterMetricas(int $empresaId): array
    {
        $metrica = $this->repository->obterMetricas($empresaId);

        return [
            'total_orcamentos' => $metrica->getTotal(),
            'valor_total' => $metrica->getValorTotal(),
            'valor_medio' => $metrica->getValorMedio(),
            'moeda' => $metrica->getMoeda(),
            'saude_geral' => $this->calcularSaudeGeral($empresaId)
        ];
    }

    /**
     * Obter análise de preços com indicadores
     */
    public function obterAnalisePrecos(int $empresaId): array
    {
        $analiseItems = $this->repository->obterAnalisePrecos($empresaId);

        $items = array_map(fn($item) => $item->toArray(), $analiseItems);

        return [
            'analise_precos' => $items,
            'total_itens_analisados' => count($items),
            'variacao_media' => $this->calcularVariacaoMedia($items),
            'maior_variacao' => $this->obterMaiorVariacao($items)
        ];
    }

    /**
     * Obter performance com indicadores
     */
    public function obterPerformanceFornecedores(int $empresaId): array
    {
        $performance = $this->repository->obterPerformanceFornecedores($empresaId);

        $items = array_map(fn($item) => $item->toArray(), $performance);

        return [
            'fornecedores' => $items,
            'total_fornecedores' => count($items),
            'confiabilidade_media' => $this->calcularConfiabilidadeMedia($items)
        ];
    }

    /**
     * Obter resumo de status
     */
    public function obterResumoStatus(int $empresaId): array
    {
        return $this->repository->obterResumoStatus($empresaId);
    }

    /**
     * Obter timeline
     */
    public function obterTimeline(int $empresaId, int $limit = 10): array
    {
        return $this->repository->obterTimeline($empresaId, $limit);
    }

    /**
     * Obter processos com maior gasto
     */
    public function obterProcessosMaiorGasto(int $empresaId, int $limit = 5): array
    {
        return $this->repository->obterProcessosMaiorGasto($empresaId, $limit);
    }

    /**
     * Comparação de períodos
     */
    public function obterComparacaoPeriodos(int $empresaId, int $meses = 12): array
    {
        $dados = $this->repository->obterComparacaoPeriodos($empresaId, $meses);

        return [
            'periodos' => $dados,
            'crescimento_total' => $this->calcularCrescimento($dados),
            'tendencia' => $this->analisarTendencia($dados)
        ];
    }

    // ====== MÉTODOS AUXILIARES DE CÁLCULO ======

    private function calcularSaudeGeral(int $empresaId): array
    {
        $status = $this->repository->obterResumoStatus($empresaId);
        
        $total = array_sum(array_column($status, 'total'));
        $aprovados = array_sum(array_filter(
            $status,
            fn($s) => $s['status'] === 'aprovado',
            ARRAY_FILTER_USE_BOTH
        ));

        $percentualAprovado = $total > 0 ? ($aprovados / $total) * 100 : 0;

        return [
            'percentual_aprovacao' => round($percentualAprovado, 2),
            'status_saude' => match(true) {
                $percentualAprovado >= 80 => 'EXCELENTE',
                $percentualAprovado >= 60 => 'BOA',
                $percentualAprovado >= 40 => 'MÉDIA',
                default => 'BAIXA'
            }
        ];
    }

    private function calcularVariacaoMedia(array $items): float
    {
        if (empty($items)) {
            return 0;
        }

        $variacoes = array_column($items, 'variacao_percentual');
        return round(array_sum($variacoes) / count($variacoes), 2);
    }

    private function obterMaiorVariacao(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        return array_reduce($items, function ($carry, $item) {
            return ($carry['variacao_percentual'] ?? 0) < ($item['variacao_percentual'] ?? 0) ? $item : $carry;
        });
    }

    private function calcularConfiabilidadeMedia(array $items): float
    {
        if (empty($items)) {
            return 0;
        }

        $confiabilidades = array_map(function ($item) {
            return match($item['confiabilidade'] ?? 'BAIXA') {
                'EXCELENTE' => 100,
                'BOA' => 75,
                'MEDIA' => 50,
                default => 25
            };
        }, $items);

        return round(array_sum($confiabilidades) / count($confiabilidades), 2);
    }

    private function calcularCrescimento(array $dados): float
    {
        if (count($dados) < 2) {
            return 0;
        }

        $primeiro = $dados[0]['valor'] ?? 0;
        $ultimo = $dados[count($dados) - 1]['valor'] ?? 0;

        if ($primeiro === 0) {
            return 0;
        }

        return round((($ultimo - $primeiro) / $primeiro) * 100, 2);
    }

    private function analisarTendencia(array $dados): string
    {
        $crescimento = $this->calcularCrescimento($dados);

        if ($crescimento > 20) {
            return 'CRESCIMENTO_FORTE';
        } elseif ($crescimento > 0) {
            return 'CRESCIMENTO_LEVE';
        } elseif ($crescimento < -20) {
            return 'REDUCAO_FORTE';
        }
        return 'ESTAVEL';
    }
}
