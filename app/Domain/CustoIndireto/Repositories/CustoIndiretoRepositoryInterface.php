<?php

namespace App\Domain\CustoIndireto\Repositories;

use App\Domain\CustoIndireto\Entities\CustoIndireto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustoIndiretoRepositoryInterface
{
    public function criar(CustoIndireto $custo): CustoIndireto;
    public function buscarPorId(int $id): ?CustoIndireto;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(CustoIndireto $custo): CustoIndireto;
    public function deletar(int $id): void;
}



