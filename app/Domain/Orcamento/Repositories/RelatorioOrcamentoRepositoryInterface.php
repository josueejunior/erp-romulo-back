<?php

namespace App\Domain\Orcamento\Repositories;

use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use Illuminate\Support\Collection;

/**
 * Repository Interface para relatórios de orçamentos
 * 
 * ✅ DDD: Separa queries (infraestrutura) de regras de negócio (Domain Service)
 * Domain Service decide O QUE buscar, Repository decide COMO buscar
 */
interface RelatorioOrcamentoRepositoryInterface
{
    /**
     * Buscar orçamentos para relatório por período
     */
    public function buscarOrcamentosPorPeriodo(int $empresaId, FiltrosRelatorio $filtros): Collection;

    /**
     * Buscar orçamentos agrupados por fornecedor
     */
    public function buscarOrcamentosPorFornecedor(int $empresaId, FiltrosRelatorio $filtros): Collection;

    /**
     * Buscar orçamentos agrupados por status
     */
    public function buscarOrcamentosPorStatus(int $empresaId, FiltrosRelatorio $filtros): Collection;
}





