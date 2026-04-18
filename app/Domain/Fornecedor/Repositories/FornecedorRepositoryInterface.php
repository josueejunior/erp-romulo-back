<?php

namespace App\Domain\Fornecedor\Repositories;

use App\Domain\Fornecedor\Entities\Fornecedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FornecedorRepositoryInterface
{
    public function criar(Fornecedor $fornecedor): Fornecedor;
    public function buscarPorId(int $id): ?Fornecedor;
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Modules\Fornecedor\Models\Fornecedor;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Fornecedor $fornecedor): Fornecedor;
    public function deletar(int $id): void;
}




