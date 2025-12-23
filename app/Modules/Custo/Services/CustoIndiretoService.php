<?php

namespace App\Modules\Custo\Services;

use App\Services\BaseService;
use App\Models\CustoIndireto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class CustoIndiretoService extends BaseService
{
    protected static string $model = CustoIndireto::class;

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'data_inicio' => $values['data_inicio'] ?? null,
            'data_fim' => $values['data_fim'] ?? null,
            'categoria' => $values['categoria'] ?? null,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        $builder = $this->createQueryBuilder();

        // Busca livre
        if (isset($params['search']) && $params['search']) {
            $search = $params['search'];
            $builder->where(function($q) use ($search) {
                $q->where('descricao', 'ilike', "%{$search}%")
                  ->orWhere('categoria', 'ilike', "%{$search}%");
            });
        }

        // Filtro por data início
        if (isset($params['data_inicio'])) {
            $builder->where('data', '>=', $params['data_inicio']);
        }

        // Filtro por data fim
        if (isset($params['data_fim'])) {
            $builder->where('data', '<=', $params['data_fim']);
        }

        // Filtro por categoria
        if (isset($params['categoria'])) {
            $builder->where('categoria', $params['categoria']);
        }

        // Ordenação
        $modelClass = static::$model;
        $createdAtColumn = defined("$modelClass::CREATED_AT") 
            ? $modelClass::CREATED_AT 
            : 'criado_em';
        $builder->orderBy('data', 'desc')->orderBy($createdAtColumn, 'desc');

        // Paginação
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'descricao' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'categoria' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return $this->validateStoreData($data);
    }

    public function resumo(array $params = []): array
    {
        $builder = $this->createQueryBuilder();

        // Filtro por data início
        if (isset($params['data_inicio'])) {
            $builder->where('data', '>=', $params['data_inicio']);
        }

        // Filtro por data fim
        if (isset($params['data_fim'])) {
            $builder->where('data', '<=', $params['data_fim']);
        }

        $total = $builder->sum('valor');
        $quantidade = $builder->count();

        // Agrupar por categoria
        $porCategoria = $builder->selectRaw('categoria, SUM(valor) as total')
            ->groupBy('categoria')
            ->get();

        return [
            'total' => round($total, 2),
            'quantidade' => $quantidade,
            'por_categoria' => $porCategoria,
        ];
    }
}


