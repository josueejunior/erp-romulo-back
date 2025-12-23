<?php

namespace App\Modules\Fornecedor\Services;

use App\Services\BaseService;
use App\Models\Fornecedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class FornecedorService extends BaseService
{
    protected static string $model = Fornecedor::class;

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'apenas_transportadoras' => $values['apenas_transportadoras'] ?? false,
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
                $q->where('razao_social', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%")
                  ->orWhere('nome_fantasia', 'like', "%{$search}%");
            });
        }

        // Filtro por transportadoras
        if (isset($params['apenas_transportadoras']) && $params['apenas_transportadoras']) {
            $builder->where('is_transportadora', true);
        }

        // Ordenação
        $builder->orderBy('razao_social');

        // Paginação
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'nome_fantasia' => 'nullable|string|max:255',
            'endereco' => 'nullable|string',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'contato' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return $this->validateStoreData($data);
    }

    public function deleteById(int|string $id): bool
    {
        $fornecedor = $this->findById($id);
        
        if (!$fornecedor) {
            return false;
        }

        if ($fornecedor->orcamentos()->count() > 0) {
            throw new \Exception('Não é possível excluir um fornecedor que possui orçamentos vinculados.');
        }

        return $fornecedor->forceDelete();
    }
}

