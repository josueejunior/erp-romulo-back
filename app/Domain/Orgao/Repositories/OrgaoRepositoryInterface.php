<?php

namespace App\Domain\Orgao\Repositories;

use App\Domain\Orgao\Entities\Orgao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrgaoRepositoryInterface
{
    public function criar(Orgao $orgao): Orgao;
    public function buscarPorId(int $id): ?Orgao;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(Orgao $orgao): Orgao;
    public function deletar(int $id): void;
}

