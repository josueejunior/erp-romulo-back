<?php

namespace App\Modules\Orgao\Services;

use App\Services\BaseService;
use App\Models\Setor;
use App\Models\Orgao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class SetorService extends BaseService
{
    protected static string $model = Setor::class;

    public function createListParamBag(array $values): array
    {
        return [
            'orgao_id' => $values['orgao_id'] ?? null,
            'search' => $values['search'] ?? null,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'with' => $values['with'] ?? ['orgao'],
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        $builder = $this->createQueryBuilder();

        // Filtro por órgão
        if (isset($params['orgao_id'])) {
            // Garantir que orgao_id seja sempre um inteiro
            $orgaoId = $params['orgao_id'];
            
            // Se for array ou objeto, tentar extrair o ID
            if (is_array($orgaoId)) {
                $orgaoId = $orgaoId['id'] ?? $orgaoId['value'] ?? null;
            } elseif (is_object($orgaoId)) {
                $orgaoId = $orgaoId->id ?? $orgaoId->value ?? null;
            }
            
            // Converter para inteiro
            $orgaoId = filter_var($orgaoId, FILTER_VALIDATE_INT);
            
            if ($orgaoId === false || $orgaoId === null) {
                throw new \Exception('ID do órgão inválido.');
            }
            
            $empresaId = $this->getEmpresaId();
            $orgao = Orgao::where('id', $orgaoId)
                ->where('empresa_id', $empresaId)
                ->first();
            
            if (!$orgao) {
                throw new \Exception('Órgão não encontrado ou não pertence à empresa ativa.');
            }
            
            $builder->where('orgao_id', $orgaoId);
        }

        // Busca livre
        if (isset($params['search']) && $params['search']) {
            $search = $params['search'];
            $builder->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Carregar relacionamentos
        if (isset($params['with']) && is_array($params['with'])) {
            $builder->with($params['with']);
        }

        // Ordenação
        $builder->orderBy('nome');

        // Paginação
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $empresaId = $this->getEmpresaId();
        
        return Validator::make($data, [
            'orgao_id' => 'required|exists:orgaos,id',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ])->after(function ($validator) use ($data, $empresaId) {
            // Validar que o órgão pertence à empresa
            $orgao = Orgao::where('id', $data['orgao_id'] ?? null)
                ->where('empresa_id', $empresaId)
                ->first();
            
            if (!$orgao) {
                $validator->errors()->add('orgao_id', 'Órgão não encontrado ou não pertence à empresa ativa.');
            }

            // Validar que o nome do setor é único para o órgão
            if (isset($data['nome']) && isset($data['orgao_id'])) {
                $exists = Setor::where('orgao_id', $data['orgao_id'])
                    ->where('empresa_id', $empresaId)
                    ->where('nome', $data['nome'])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nome', 'Já existe um setor com este nome para este órgão.');
                }
            }
        });
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        $empresaId = $this->getEmpresaId();
        $setor = $this->findById($id);
        
        return Validator::make($data, [
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ])->after(function ($validator) use ($data, $id, $empresaId, $setor) {
            if ($setor && isset($data['nome'])) {
                // Validar que o nome do setor é único para o órgão (exceto o próprio setor)
                $exists = Setor::where('orgao_id', $setor->orgao_id)
                    ->where('empresa_id', $empresaId)
                    ->where('nome', $data['nome'])
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nome', 'Já existe um setor com este nome para este órgão.');
                }
            }
        });
    }

    public function deleteById(int|string $id): bool
    {
        $setor = $this->findById($id);
        
        if (!$setor) {
            return false;
        }

        if ($setor->processos()->count() > 0) {
            throw new \Exception('Não é possível excluir um setor que possui processos vinculados.');
        }

        return $setor->forceDelete();
    }
}




