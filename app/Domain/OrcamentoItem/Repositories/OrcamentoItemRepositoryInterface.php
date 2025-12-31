<?php

namespace App\Domain\OrcamentoItem\Repositories;

use App\Domain\OrcamentoItem\Entities\OrcamentoItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface do repositório de OrcamentoItem
 */
interface OrcamentoItemRepositoryInterface
{
    public function criar(OrcamentoItem $orcamentoItem): OrcamentoItem;
    public function buscarPorId(int $id): ?OrcamentoItem;
    public function buscarPorOrcamento(int $orcamentoId): array;
    public function buscarPorProcessoItem(int $processoItemId): array;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(OrcamentoItem $orcamentoItem): OrcamentoItem;
    public function deletar(int $id): void;
    public function marcarComoEscolhido(int $id): OrcamentoItem;
    public function desmarcarEscolhido(int $orcamentoId, int $processoItemId): void;
}


