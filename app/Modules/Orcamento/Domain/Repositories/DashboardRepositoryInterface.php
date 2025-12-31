<?php

namespace App\Modules\Orcamento\Domain\Repositories;

use App\Modules\Orcamento\Domain\ValueObjects\MetricaOrcamento;
use App\Modules\Orcamento\Domain\ValueObjects\AnalisePrecoItem;
use App\Modules\Orcamento\Domain\ValueObjects\PerformanceFornecedor;
use App\Modules\Orcamento\Domain\ValueObjects\ResumoStatusOrcamento;

interface DashboardRepositoryInterface
{
    /**
     * Obter métricas gerais de orçamentos
     */
    public function obterMetricas(int $empresaId): MetricaOrcamento;

    /**
     * Obter análise de preços por item
     */
    public function obterAnalisePrecos(int $empresaId): array;

    /**
     * Obter performance de fornecedores
     */
    public function obterPerformanceFornecedores(int $empresaId): array;

    /**
     * Obter resumo de status dos orçamentos
     */
    public function obterResumoStatus(int $empresaId): array;

    /**
     * Obter timeline de orçamentos recentes
     */
    public function obterTimeline(int $empresaId, int $limit = 10): array;

    /**
     * Obter comparação de períodos
     */
    public function obterComparacaoPeriodos(int $empresaId, int $meses = 12): array;

    /**
     * Obter processos com maior gasto
     */
    public function obterProcessosMaiorGasto(int $empresaId, int $limit = 5): array;
}
