<?php

namespace App\Domain\DocumentoHabilitacao\Repositories;

use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentoHabilitacaoRepositoryInterface
{
    public function criar(DocumentoHabilitacao $documento): DocumentoHabilitacao;
    public function buscarPorId(int $id): ?DocumentoHabilitacao;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(DocumentoHabilitacao $documento): DocumentoHabilitacao;
    public function deletar(int $id): void;
}


