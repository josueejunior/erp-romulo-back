<?php

namespace App\Domain\NotaFiscal\Repositories;

use App\Domain\NotaFiscal\Entities\NotaFiscal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotaFiscalRepositoryInterface
{
    public function criar(NotaFiscal $notaFiscal): NotaFiscal;
    public function buscarPorId(int $id): ?NotaFiscal;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(NotaFiscal $notaFiscal): NotaFiscal;
    public function deletar(int $id): void;
}

