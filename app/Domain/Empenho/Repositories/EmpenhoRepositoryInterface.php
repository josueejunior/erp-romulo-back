<?php

namespace App\Domain\Empenho\Repositories;

use App\Domain\Empenho\Entities\Empenho;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmpenhoRepositoryInterface
{
    public function criar(Empenho $empenho): Empenho;
    public function buscarPorId(int $id): ?Empenho;
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Modules\Empenho\Models\Empenho;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Empenho $empenho): Empenho;
    public function deletar(int $id): void;
}




