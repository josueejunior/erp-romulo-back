<?php

namespace App\Domain\Orcamento\Repositories;

use App\Domain\Orcamento\Entities\Orcamento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrcamentoRepositoryInterface
{
    public function criar(Orcamento $orcamento): Orcamento;
    public function buscarPorId(int $id): ?Orcamento;
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Models\Orcamento;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Orcamento $orcamento): Orcamento;
    public function deletar(int $id): void;
}

