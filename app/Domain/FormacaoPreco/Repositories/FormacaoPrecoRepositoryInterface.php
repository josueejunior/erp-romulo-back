<?php

namespace App\Domain\FormacaoPreco\Repositories;

use App\Domain\FormacaoPreco\Entities\FormacaoPreco;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FormacaoPrecoRepositoryInterface
{
    public function criar(FormacaoPreco $formacao): FormacaoPreco;
    public function buscarPorId(int $id): ?FormacaoPreco;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(FormacaoPreco $formacao): FormacaoPreco;
    public function deletar(int $id): void;
}

