<?php

namespace App\Domain\FormacaoPreco\Repositories;

use App\Domain\FormacaoPreco\Entities\FormacaoPreco;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FormacaoPrecoRepositoryInterface
{
    public function criar(FormacaoPreco $formacao): FormacaoPreco;
    public function buscarPorId(int $id): ?FormacaoPreco;
    public function buscarModeloPorId(int $id, array $with = []): ?\App\Modules\Orcamento\Models\FormacaoPreco;
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;
    public function atualizar(FormacaoPreco $formacao): FormacaoPreco;
    public function deletar(int $id): void;
    
    /**
     * Buscar formação de preço por contexto (processo, item, orçamento)
     * 
     * ✅ DDD: Método específico para busca contextual
     */
    public function buscarPorContexto(int $processoId, int $itemId, ?int $orcamentoId = null): ?\App\Modules\Orcamento\Models\FormacaoPreco;
    
    /**
     * Buscar ou criar formação de preço
     * 
     * ✅ DDD: Operação atômica para salvar
     */
    public function buscarOuCriar(array $dados): \App\Modules\Orcamento\Models\FormacaoPreco;
}




