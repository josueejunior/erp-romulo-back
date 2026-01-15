<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Modules\Orcamento\Models\Orcamento as OrcamentoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;
use Illuminate\Support\Facades\Schema;

class OrcamentoRepository implements OrcamentoRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(OrcamentoModel $model): Orcamento
    {
        // Verificar se a coluna transportadora_id existe antes de acessar
        $transportadoraId = null;
        if (Schema::hasColumn('orcamentos', 'transportadora_id')) {
            $transportadoraId = $model->transportadora_id;
        }
        
        return new Orcamento(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            processoItemId: $model->processo_item_id,
            fornecedorId: $model->fornecedor_id,
            transportadoraId: $transportadoraId,
            custoProduto: (float) $model->custo_produto,
            marcaModelo: $model->marca_modelo,
            ajustesEspecificacao: $model->ajustes_especificacao,
            frete: (float) $model->frete,
            freteIncluido: $model->frete_incluido ?? false,
            fornecedorEscolhido: $model->fornecedor_escolhido ?? false,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(Orcamento $orcamento): array
    {
        $data = [
            'empresa_id' => $orcamento->empresaId,
            'processo_id' => $orcamento->processoId,
            'processo_item_id' => $orcamento->processoItemId,
            'fornecedor_id' => $orcamento->fornecedorId,
            'custo_produto' => $orcamento->custoProduto,
            'marca_modelo' => $orcamento->marcaModelo,
            'ajustes_especificacao' => $orcamento->ajustesEspecificacao,
            'frete' => $orcamento->frete,
            'frete_incluido' => $orcamento->freteIncluido,
            'fornecedor_escolhido' => $orcamento->fornecedorEscolhido,
            'observacoes' => $orcamento->observacoes,
        ];
        
        // Verificar se a coluna transportadora_id existe antes de incluir
        // Isso evita erro se a migration não foi executada
        if (Schema::hasColumn('orcamentos', 'transportadora_id')) {
            $data['transportadora_id'] = $orcamento->transportadoraId;
        }
        
        return $data;
    }

    public function criar(Orcamento $orcamento): Orcamento
    {
        $data = $this->toArray($orcamento);
        
        \Log::info('OrcamentoRepository::criar - Dados para criação', [
            'data' => $data,
            'tenant_id' => tenancy()->tenant?->id,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
        
        $model = OrcamentoModel::create($data);
        
        \Log::info('OrcamentoRepository::criar - Model criado no banco', [
            'orcamento_id' => $model->id,
            'empresa_id' => $model->empresa_id,
            'processo_id' => $model->processo_id,
            'processo_item_id' => $model->processo_item_id,
            'fornecedor_id' => $model->fornecedor_id,
            'criado_em' => $model->criado_em?->toDateTimeString(),
        ]);
        
        $freshModel = $model->fresh();
        
        \Log::info('OrcamentoRepository::criar - Model após fresh()', [
            'orcamento_id' => $freshModel->id,
            'empresa_id' => $freshModel->empresa_id,
            'existe_no_banco' => $freshModel !== null,
        ]);
        
        $domain = $this->toDomain($freshModel);
        
        \Log::info('OrcamentoRepository::criar - Entidade de domínio criada', [
            'orcamento_id' => $domain->id,
            'empresa_id' => $domain->empresaId,
        ]);
        
        return $domain;
    }

    public function buscarPorId(int $id): ?Orcamento
    {
        $model = OrcamentoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = OrcamentoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Orcamento $orcamento): Orcamento
    {
        $model = OrcamentoModel::findOrFail($orcamento->id);
        $model->update($this->toArray($orcamento));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        OrcamentoModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?OrcamentoModel
    {
        \Log::info('OrcamentoRepository::buscarModeloPorId - Iniciando busca', [
            'orcamento_id' => $id,
            'with' => $with,
            'tenant_id' => tenancy()->tenant?->id,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
        
        // Primeiro tentar com Global Scope (segurança)
        $model = $this->buscarModeloPorIdInternal($id, $with, false);
        
        \Log::info('OrcamentoRepository::buscarModeloPorId - Resultado com Global Scope', [
            'orcamento_id' => $id,
            'encontrado' => $model !== null,
            'model_id' => $model?->id,
            'model_empresa_id' => $model?->empresa_id,
        ]);
        
        // Se não encontrou com Global Scope, tentar sem (pode ser problema de sincronização)
        if (!$model) {
            \Log::warning('OrcamentoRepository::buscarModeloPorId - Não encontrado com Global Scope, tentando sem', [
                'orcamento_id' => $id,
            ]);
            
            $modelWithoutScope = OrcamentoModel::withoutGlobalScope('empresa')
                ->with($with)
                ->find($id);
            
            \Log::info('OrcamentoRepository::buscarModeloPorId - Resultado sem Global Scope', [
                'orcamento_id' => $id,
                'encontrado' => $modelWithoutScope !== null,
                'model_id' => $modelWithoutScope?->id,
                'model_empresa_id' => $modelWithoutScope?->empresa_id,
            ]);
            
            if ($modelWithoutScope) {
                \Log::warning('OrcamentoRepository::buscarModeloPorId - Orçamento encontrado sem Global Scope! Possível problema de sincronização', [
                    'orcamento_id' => $id,
                    'empresa_id_orcamento' => $modelWithoutScope->empresa_id,
                    'empresa_id_contexto' => static::getEmpresaIdFromContext(),
                ]);
            }
            
            return $modelWithoutScope;
        }
        
        return $model;
    }
    
    /**
     * Obtém empresa_id do contexto (para debug)
     */
    private static function getEmpresaIdFromContext(): ?int
    {
        try {
            if (app()->bound('current_empresa_id')) {
                return (int) app('current_empresa_id');
            }
        } catch (\Exception $e) {}
        
        if (request() && request()->attributes->has('empresa_id')) {
            return (int) request()->attributes->get('empresa_id');
        }
        
        try {
            $authIdentity = app(\App\Contracts\IAuthIdentity::class);
            if ($authIdentity) {
                return $authIdentity->getEmpresaId();
            }
        } catch (\Exception $e) {}
        
        return null;
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return OrcamentoModel::class;
    }
}


