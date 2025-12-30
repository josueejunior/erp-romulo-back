<?php

namespace App\Domain\NotaFiscal\Repositories;

use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Modules\NotaFiscal\Models\NotaFiscal as NotaFiscalModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotaFiscalRepositoryInterface
{
    public function criar(NotaFiscal $notaFiscal): NotaFiscal;
    public function buscarPorId(int $id): ?NotaFiscal;
    public function buscarModeloPorId(int $id, array $with = []): ?NotaFiscalModel;
    public function buscarPorProcesso(int $processoId, array $filtros = []): array;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(NotaFiscal $notaFiscal): NotaFiscal;
    public function deletar(int $id): void;
}

