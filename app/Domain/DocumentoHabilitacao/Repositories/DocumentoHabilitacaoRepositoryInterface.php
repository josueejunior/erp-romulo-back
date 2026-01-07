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
    public function buscarVencendo(int $empresaId, int $dias = 30): array;
    public function buscarVencidos(int $empresaId): array;
    
    /**
     * Buscar documentos ativos por empresa (compatibilidade)
     * @return \Illuminate\Support\Collection
     */
    public function buscarAtivosPorEmpresa(int $empresaId): \Illuminate\Support\Collection;
    
    /**
     * Buscar modelo Eloquent por ID (compatibilidade)
     * @return \App\Modules\Documento\Models\DocumentoHabilitacao|null
     */
    public function buscarModeloPorId(int $id): ?\App\Modules\Documento\Models\DocumentoHabilitacao;
}


