<?php

namespace App\Domain\Setor\Repositories;

use App\Domain\Setor\Entities\Setor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SetorRepositoryInterface
{
    public function criar(Setor $setor): Setor;
    public function buscarPorId(int $id): ?Setor;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Setor $setor): Setor;
    public function deletar(int $id): void;
}

