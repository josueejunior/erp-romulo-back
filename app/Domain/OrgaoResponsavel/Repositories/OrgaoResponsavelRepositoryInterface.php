<?php

namespace App\Domain\OrgaoResponsavel\Repositories;

use App\Domain\OrgaoResponsavel\Entities\OrgaoResponsavel;
use App\Modules\Orgao\Models\OrgaoResponsavel as OrgaoResponsavelModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrgaoResponsavelRepositoryInterface
{
    public function criar(OrgaoResponsavel $responsavel): OrgaoResponsavel;
    public function buscarPorId(int $id): ?OrgaoResponsavel;
    public function buscarModeloPorId(int $id, array $with = []): ?OrgaoResponsavelModel;
    public function buscarPorOrgao(int $orgaoId): array;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(OrgaoResponsavel $responsavel): OrgaoResponsavel;
    public function deletar(int $id): void;
}

