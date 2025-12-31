<?php

namespace App\Domain\AutorizacaoFornecimento\Repositories;

use App\Domain\AutorizacaoFornecimento\Entities\AutorizacaoFornecimento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AutorizacaoFornecimentoRepositoryInterface
{
    public function criar(AutorizacaoFornecimento $autorizacao): AutorizacaoFornecimento;
    public function buscarPorId(int $id): ?AutorizacaoFornecimento;
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Models\AutorizacaoFornecimento;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(AutorizacaoFornecimento $autorizacao): AutorizacaoFornecimento;
    public function deletar(int $id): void;
}


