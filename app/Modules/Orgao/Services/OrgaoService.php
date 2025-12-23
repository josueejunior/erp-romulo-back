<?php

namespace App\Modules\Orgao\Services;

use App\Services\BaseService;
use App\Models\Orgao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class OrgaoService extends BaseService
{
    protected static string $model = Orgao::class;

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'with' => $values['with'] ?? ['setors'],
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        try {
            $builder = $this->createQueryBuilder();

            // Busca livre
            if (isset($params['search']) && $params['search']) {
                $search = $params['search'];
                $builder->where(function($q) use ($search) {
                    $q->where('razao_social', 'like', "%{$search}%")
                      ->orWhere('cnpj', 'like', "%{$search}%");
                });
            }

            // Carregar relacionamentos
            if (isset($params['with']) && is_array($params['with'])) {
                $builder->with($params['with']);
            }

            // Ordenação
            $builder->orderBy('razao_social');

            // Paginação
            $perPage = $params['per_page'] ?? 15;
            $page = $params['page'] ?? 1;

            return $builder->paginate($perPage, ['*'], 'page', $page);
        } catch (\Exception $e) {
            \Log::error('Erro no OrgaoService->list()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $params,
                'empresa_id' => $this->getEmpresaId()
            ]);
            throw $e;
        }
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'telefones' => 'nullable|array',
            'telefones.*' => 'string|max:20',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return $this->validateStoreData($data);
    }

    public function deleteById(int|string $id): bool
    {
        $orgao = $this->findById($id);
        
        if (!$orgao) {
            return false;
        }

        if ($orgao->processos()->count() > 0) {
            throw new \Exception('Não é possível excluir um órgão que possui processos vinculados.');
        }

        return $orgao->forceDelete();
    }
}


