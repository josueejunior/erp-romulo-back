<?php

namespace App\Services;

use App\Contracts\IService;
use App\Services\Traits\CheckEmpresaUsage;
use App\Services\Traits\AuthScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Classe base para services
 * Aplica filtro automático por empresa_id em todas as queries
 * Similar ao DataService do sistema de referência
 */
abstract class BaseService implements IService
{
    use CheckEmpresaUsage, AuthScope;

    /**
     * Model class name
     */
    protected static string $model;

    /**
     * Aplicar filtro por empresa_id automaticamente no builder
     * Similar ao applyBuilderWhereCliente() do sistema de referência
     * 
     * NOTA: Se o modelo já tem o global scope 'empresa' (HasEmpresaScope),
     * não aplica o filtro novamente para evitar duplicação.
     */
    protected function applyBuilderWhereEmpresa(
        Builder $builder,
        string|Model $model = null,
        string $alias = null,
        bool $nullable = false
    ): Builder {
        $model = $model ?? static::$model;
        
        if (!$this->hasEmpresaUsage($model)) {
            return $builder;
        }

        // Verificar se o modelo já tem o global scope 'empresa' (HasEmpresaScope)
        // Se tiver, não aplicar o filtro novamente para evitar duplicação
        // Verificar se o modelo usa o trait HasEmpresaScope
        $modelClass = is_string($model) ? $model : get_class($model);
        $traits = class_uses_recursive($modelClass);
        if (in_array(\App\Models\Concerns\HasEmpresaScope::class, $traits)) {
            // O modelo já tem HasEmpresaScope, não precisa aplicar filtro novamente
            return $builder;
        }

        $column = $this->getEmpresaField($model);
        $empresaId = $this->getEmpresaId();
        
        if (!$empresaId) {
            // Se não tem empresa_id, retornar query vazia para segurança
            return $builder->whereRaw('1 = 0');
        }

        $column = $alias ? "$alias.$column" : $column;
        
        $condition = static fn(Builder $builder) => $builder
            ->where($column, '=', $empresaId)
            ->when($nullable, static fn() => $builder->orWhereNull($column));

        $nullable ? $builder->where($condition) : $condition($builder);
        
        return $builder;
    }

    /**
     * Criar query builder base com filtro automático
     */
    protected function createQueryBuilder(
        string|Model $model = null,
        bool $validateEmpresa = true
    ): Builder {
        $model = $model ?? static::$model;
        $builder = $model::query();
        
        if ($validateEmpresa) {
            $this->applyBuilderWhereEmpresa($builder, $model);
        }
        
        return $builder;
    }

    /**
     * Criar parâmetros para busca por ID
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE, não precisa estar nos params
     */
    public function createFindByIdParamBag(array $values): array
    {
        return [
            'with' => $values['with'] ?? [],
        ];
    }

    /**
     * Buscar registro por ID
     */
    public function findById(int|string $id, array $params = []): ?Model
    {
        $builder = $this->createQueryBuilder();
        
        // Carregar relacionamentos
        if (isset($params['with']) && is_array($params['with'])) {
            $builder->with($params['with']);
        }
        
        return $builder->find($id);
    }

    /**
     * Criar parâmetros para listagem
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE, não precisa estar nos params
     */
    public function createListParamBag(array $values): array
    {
        return [
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'with' => $values['with'] ?? [],
        ];
    }

    /**
     * Listar registros com paginação
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $builder = $this->createQueryBuilder();
        
        // Carregar relacionamentos
        if (isset($params['with']) && is_array($params['with'])) {
            $builder->with($params['with']);
        }
        
        // Ordenação padrão (usar constante do modelo ou padrão criado_em)
        $modelClass = static::$model;
        $createdAtColumn = defined("$modelClass::CREATED_AT") 
            ? $modelClass::CREATED_AT 
            : 'criado_em';
        $builder->orderBy($createdAtColumn, 'desc');
        
        // Paginação
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;
        
        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Validar dados para criação
     * Deve ser implementado no service filho
     */
    abstract public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator;

    /**
     * Criar novo registro
     */
    public function store(array $data): Model
    {
        // Adicionar empresa_id automaticamente se não estiver presente
        if (!isset($data['empresa_id'])) {
            $empresaId = $this->getEmpresaId();
            if ($empresaId) {
                $data['empresa_id'] = $empresaId;
            }
        }
        
        // Debug: Log antes de criar
        \Log::debug('BaseService->store()', [
            'model' => static::$model,
            'empresa_id_in_data' => $data['empresa_id'] ?? null,
            'getEmpresaId()' => $this->getEmpresaId(),
            'data_keys' => array_keys($data),
        ]);
        
        $model = static::$model;
        $created = $model::create($data);
        
        // Debug: Log após criar
        \Log::debug('BaseService->store() criado', [
            'model' => static::$model,
            'created_id' => $created->id,
            'created_empresa_id' => $created->empresa_id ?? null,
        ]);
        
        return $created;
    }

    /**
     * Validar dados para atualização
     * Deve ser implementado no service filho
     */
    abstract public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator;

    /**
     * Atualizar registro
     */
    public function update(int|string $id, array $data): Model
    {
        $model = $this->findById($id);
        
        if (!$model) {
            throw new \Exception('Registro não encontrado');
        }

        // Não permitir alterar empresa_id
        unset($data['empresa_id']);

        // Filtrar apenas campos que estão no fillable do modelo
        $fillable = $model->getFillable();
        $filteredData = [];
        
        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                // Permitir arrays vazios e null para campos que podem ser arrays
                // Se o valor for null e o campo não estiver presente, não incluir
                // Mas se estiver presente (mesmo que null), incluir para permitir limpar o campo
                $filteredData[$field] = $data[$field];
            }
        }

        // Log para debug (remover em produção se necessário)
        \Log::debug('BaseService->update()', [
            'model' => static::$model,
            'id' => $id,
            'data_received' => $data,
            'fillable' => $fillable,
            'filtered_data' => $filteredData,
        ]);

        $model->update($filteredData);
        $model->refresh();

        return $model;
    }

    /**
     * Excluir registro por ID
     */
    public function deleteById(int|string $id): bool
    {
        $model = $this->findById($id);
        
        if (!$model) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Excluir múltiplos registros
     */
    public function deleteByIds(array $ids): int
    {
        $empresaId = $this->getEmpresaId();
        
        if (!$empresaId) {
            return 0;
        }
        
        $model = static::$model;
        $empresaField = $this->getEmpresaField($model);
        
        $deleted = $model::where($empresaField, $empresaId)
            ->whereIn('id', $ids)
            ->delete();

        return $deleted;
    }
}

