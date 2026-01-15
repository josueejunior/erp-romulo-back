<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\ProcessoItem\Entities\ProcessoItem;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Modules\Processo\Models\ProcessoItem as ProcessoItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class ProcessoItemRepository implements ProcessoItemRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(ProcessoItemModel $model): ProcessoItem
    {
        return new ProcessoItem(
            id: $model->id,
            processoId: $model->processo_id,
            empresaId: $model->empresa_id,
            fornecedorId: $model->fornecedor_id,
            transportadoraId: $model->transportadora_id,
            numeroItem: $model->numero_item,
            codigoInterno: $model->codigo_interno,
            quantidade: (float) $model->quantidade,
            unidade: $model->unidade,
            especificacaoTecnica: $model->especificacao_tecnica,
            marcaModeloReferencia: $model->marca_modelo_referencia,
            observacoesEdital: $model->observacoes_edital,
            exigeAtestado: (bool) $model->exige_atestado,
            quantidadeMinimaAtestado: $model->quantidade_minima_atestado ? (float) $model->quantidade_minima_atestado : null,
            quantidadeAtestadoCapTecnica: $model->quantidade_atestado_cap_tecnica ? (float) $model->quantidade_atestado_cap_tecnica : null,
            valorEstimado: (float) $model->valor_estimado,
            valorEstimadoTotal: (float) $model->valor_estimado_total,
            fonteValor: $model->fonte_valor,
            valorMinimoVenda: (float) $model->valor_minimo_venda,
            valorFinalSessao: (float) $model->valor_final_sessao,
            valorArrematado: (float) $model->valor_arrematado,
            dataDisputa: $model->data_disputa ? Carbon::parse($model->data_disputa) : null,
            valorNegociado: (float) $model->valor_negociado,
            classificacao: $model->classificacao,
            statusItem: $model->status_item,
            situacaoFinal: $model->situacao_final,
            chanceArremate: $model->chance_arremate,
            chancePercentual: $model->chance_percentual ? (float) $model->chance_percentual : null,
            temChance: (bool) $model->tem_chance,
            lembretes: $model->lembretes,
            observacoes: $model->observacoes,
            valorVencido: (float) $model->valor_vencido,
            valorEmpenhado: (float) $model->valor_empenhado,
            valorFaturado: (float) $model->valor_faturado,
            valorPago: (float) $model->valor_pago,
            saldoAberto: (float) $model->saldo_aberto,
            lucroBruto: (float) $model->lucro_bruto,
            lucroLiquido: (float) $model->lucro_liquido,
        );
    }

    private function toArray(ProcessoItem $processoItem): array
    {
        return [
            'empresa_id' => $processoItem->empresaId,
            'processo_id' => $processoItem->processoId,
            'fornecedor_id' => $processoItem->fornecedorId,
            'transportadora_id' => $processoItem->transportadoraId,
            'numero_item' => $processoItem->numeroItem,
            'codigo_interno' => $processoItem->codigoInterno,
            'quantidade' => $processoItem->quantidade,
            'unidade' => $processoItem->unidade,
            'especificacao_tecnica' => $processoItem->especificacaoTecnica,
            'marca_modelo_referencia' => $processoItem->marcaModeloReferencia,
            'observacoes_edital' => $processoItem->observacoesEdital,
            'exige_atestado' => $processoItem->exigeAtestado,
            'quantidade_minima_atestado' => $processoItem->quantidadeMinimaAtestado,
            'quantidade_atestado_cap_tecnica' => $processoItem->quantidadeAtestadoCapTecnica,
            'valor_estimado' => $processoItem->valorEstimado,
            'valor_estimado_total' => $processoItem->valorEstimadoTotal,
            'fonte_valor' => $processoItem->fonteValor,
            'valor_minimo_venda' => $processoItem->valorMinimoVenda,
            'valor_final_sessao' => $processoItem->valorFinalSessao,
            'valor_arrematado' => $processoItem->valorArrematado,
            'data_disputa' => $processoItem->dataDisputa?->toDateString(),
            'valor_negociado' => $processoItem->valorNegociado,
            'classificacao' => $processoItem->classificacao,
            'status_item' => $processoItem->statusItem,
            'situacao_final' => $processoItem->situacaoFinal,
            'chance_arremate' => $processoItem->chanceArremate,
            'chance_percentual' => $processoItem->chancePercentual,
            'tem_chance' => $processoItem->temChance,
            'lembretes' => $processoItem->lembretes,
            'observacoes' => $processoItem->observacoes,
            'valor_vencido' => $processoItem->valorVencido,
            'valor_empenhado' => $processoItem->valorEmpenhado,
            'valor_faturado' => $processoItem->valorFaturado,
            'valor_pago' => $processoItem->valorPago,
            'saldo_aberto' => $processoItem->saldoAberto,
            'lucro_bruto' => $processoItem->lucroBruto,
            'lucro_liquido' => $processoItem->lucroLiquido,
        ];
    }

    public function criar(ProcessoItem $processoItem): ProcessoItem
    {
        $data = $this->toArray($processoItem);
        $model = ProcessoItemModel::create($data);
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?ProcessoItem
    {
        $model = ProcessoItemModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorProcesso(int $processoId): array
    {
        $models = ProcessoItemModel::where('processo_id', $processoId)->get();
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = ProcessoItemModel::query();

        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        if (isset($filtros['status_item'])) {
            $query->where('status_item', $filtros['status_item']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('numero_item', 'asc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(ProcessoItem $processoItem): ProcessoItem
    {
        // ✅ CORREÇÃO CRÍTICA: Garantir que estamos atualizando APENAS o item específico pelo ID
        // IMPORTANTE: Usar withoutGlobalScope temporariamente se necessário, mas validar empresa_id manualmente
        
        // Buscar modelo com validação explícita de empresa_id
        $model = ProcessoItemModel::where('id', $processoItem->id)
            ->where('empresa_id', $processoItem->empresaId)
            ->firstOrFail();
        
        // Log para debug quando houver problemas de duplicatas
        \Log::debug('ProcessoItemRepository::atualizar()', [
            'item_id' => $processoItem->id,
            'processo_id' => $processoItem->processoId,
            'numero_item' => $processoItem->numeroItem,
            'empresa_id' => $processoItem->empresaId,
            'model_antes_update' => [
                'id' => $model->id,
                'processo_id' => $model->processo_id,
                'numero_item' => $model->numero_item,
                'empresa_id' => $model->empresa_id,
                'especificacao_tecnica' => substr($model->especificacao_tecnica ?? '', 0, 50),
            ],
        ]);
        
        // ✅ CRÍTICO: Converter para array SEM incluir o ID
        $dataToUpdate = $this->toArray($processoItem);
        
        // ✅ GARANTIA ABSOLUTA: Atualizar usando o modelo encontrado (mais seguro que update em massa)
        // Isso garante que apenas UM registro será afetado
        $model->fill($dataToUpdate);
        $model->save();
        
        // Verificar se outros itens foram afetados acidentalmente (duplicatas com mesmo numero_item)
        if ($processoItem->numeroItem) {
            $outrosItensComMesmoNumero = ProcessoItemModel::where('id', '!=', $processoItem->id)
                ->where('processo_id', $processoItem->processoId)
                ->where('numero_item', $processoItem->numeroItem)
                ->where('empresa_id', $processoItem->empresaId)
                ->count();
            
            if ($outrosItensComMesmoNumero > 0) {
                \Log::warning('ProcessoItemRepository::atualizar() - Itens duplicados detectados!', [
                    'item_id' => $processoItem->id,
                    'numero_item' => $processoItem->numeroItem,
                    'processo_id' => $processoItem->processoId,
                    'duplicatas_encontradas' => $outrosItensComMesmoNumero,
                ]);
            }
        }
        
        // Recarregar para garantir dados atualizados
        $model->refresh();
        
        // Verificar se o modelo atualizado corresponde ao esperado
        if ($model->id !== $processoItem->id || $model->empresa_id !== $processoItem->empresaId) {
            \Log::error('ProcessoItemRepository::atualizar() - Modelo incorreto após update!', [
                'esperado' => [
                    'id' => $processoItem->id,
                    'empresa_id' => $processoItem->empresaId,
                ],
                'retornado' => [
                    'id' => $model->id,
                    'empresa_id' => $model->empresa_id,
                ],
            ]);
            throw new \RuntimeException("Inconsistência detectada após atualização do item {$processoItem->id}");
        }
        
        return $this->toDomain($model);
    }

    public function deletar(int $id): void
    {
        ProcessoItemModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?ProcessoItemModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return ProcessoItemModel::class;
    }
}


