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
}


