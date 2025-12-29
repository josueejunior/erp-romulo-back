<?php

namespace App\Domain\ProcessoItem\Repositories;

use App\Domain\ProcessoItem\Entities\ProcessoItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface do repositório de ProcessoItem
 */
interface ProcessoItemRepositoryInterface
{
    public function criar(ProcessoItem $processoItem): ProcessoItem;
    public function buscarPorId(int $id): ?ProcessoItem;
    public function buscarPorProcesso(int $processoId): array;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(ProcessoItem $processoItem): ProcessoItem;
    public function deletar(int $id): void;
}

