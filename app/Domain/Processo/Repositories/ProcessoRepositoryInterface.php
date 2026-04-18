<?php

namespace App\Domain\Processo\Repositories;

use App\Domain\Processo\Entities\Processo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface do Repository de Processo
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 */
interface ProcessoRepositoryInterface
{
    /**
     * Criar um novo processo
     */
    public function criar(Processo $processo): Processo;

    /**
     * Buscar processo por ID
     */
    public function buscarPorId(int $id): ?Processo;

    /**
     * Buscar processos com filtros
     */
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;

    /**
     * Atualizar processo
     */
    public function atualizar(Processo $processo): Processo;

    /**
     * Deletar processo (soft delete)
     */
    public function deletar(int $id): void;

    /**
     * Obter resumo de processos
     */
    public function obterResumo(array $filtros = []): array;

    /**
     * Obter totais financeiros (valor vencido e lucro estimado)
     */
    public function obterTotaisFinanceiros(array $filtros = []): array;

    /**
     * Buscar modelo Eloquent por ID (compatibilidade)
     * @param int $id
     * @param array $with Relacionamentos para eager loading
     * @return \App\Modules\Processo\Models\Processo|null
     */
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Modules\Processo\Models\Processo;

    /**
     * Buscar modelos Eloquent com filtros (para calendário e listagens especiais)
     * @param array $filtros Filtros a aplicar (empresa_id, status, data_hora_sessao_publica_inicio, data_hora_sessao_publica_fim)
     * @param array $with Relacionamentos para eager loading
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function buscarModelosComFiltros(array $filtros = [], array $with = []): \Illuminate\Database\Eloquent\Collection;
}




