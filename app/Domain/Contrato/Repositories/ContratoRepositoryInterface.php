<?php

namespace App\Domain\Contrato\Repositories;

use App\Domain\Contrato\Entities\Contrato;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContratoRepositoryInterface
{
    public function criar(Contrato $contrato): Contrato;
    public function buscarPorId(int $id): ?Contrato;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Contrato $contrato): Contrato;
    public function deletar(int $id): void;
}

