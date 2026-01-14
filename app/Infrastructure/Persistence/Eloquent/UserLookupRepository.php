<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\UsersLookup\Entities\UserLookup;
use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Models\UserLookup as UserLookupModel;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do Repository de UserLookup usando Eloquent
 */
class UserLookupRepository implements UserLookupRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(UserLookupModel $model): UserLookup
    {
        return new UserLookup(
            id: $model->id,
            email: $model->email,
            cnpj: $model->cnpj,
            tenantId: $model->tenant_id,
            userId: $model->user_id,
            empresaId: $model->empresa_id,
            status: $model->status,
        );
    }
    
    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(UserLookup $lookup): array
    {
        return [
            'email' => $lookup->email,
            'cnpj' => $lookup->cnpj,
            'tenant_id' => $lookup->tenantId,
            'user_id' => $lookup->userId,
            'empresa_id' => $lookup->empresaId,
            'status' => $lookup->status,
        ];
    }
    
    public function buscarPorEmail(string $email): ?UserLookup
    {
        $model = UserLookupModel::where('email', $email)
            ->whereNull('deleted_at')
            ->first();
            
        return $model ? $this->toDomain($model) : null;
    }
    
    public function buscarPorCnpj(string $cnpj): ?UserLookup
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        
        $model = UserLookupModel::where('cnpj', $cnpjLimpo)
            ->whereNull('deleted_at')
            ->first();
            
        return $model ? $this->toDomain($model) : null;
    }
    
    public function buscarAtivosPorEmail(string $email): array
    {
        $models = UserLookupModel::where('email', $email)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->get();
            
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    public function buscarAtivosPorCnpj(string $cnpj): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        
        $models = UserLookupModel::where('cnpj', $cnpjLimpo)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->get();
            
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    public function buscarTodosPorEmail(string $email): array
    {
        $models = UserLookupModel::where('email', $email)
            ->whereNull('deleted_at')
            ->get();
            
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    public function criar(UserLookup $lookup): UserLookup
    {
        $data = $this->toArray($lookup);
        
        $model = UserLookupModel::create($data);
        
        Log::debug('UserLookupRepository: Registro criado', [
            'id' => $model->id,
            'email' => $model->email,
            'cnpj' => $model->cnpj,
            'tenant_id' => $model->tenant_id,
        ]);
        
        return $this->toDomain($model);
    }
    
    public function atualizar(UserLookup $lookup): UserLookup
    {
        if (!$lookup->id) {
            throw new \RuntimeException('ID é obrigatório para atualizar UserLookup');
        }
        
        $model = UserLookupModel::findOrFail($lookup->id);
        $model->update($this->toArray($lookup));
        
        return $this->toDomain($model->fresh());
    }
    
    public function deletar(int $id): void
    {
        $model = UserLookupModel::findOrFail($id);
        $model->delete();
        
        Log::debug('UserLookupRepository: Registro deletado', [
            'id' => $id,
        ]);
    }
    
    public function marcarComoInativo(int $id): void
    {
        $model = UserLookupModel::findOrFail($id);
        $model->update(['status' => 'inativo']);
        
        Log::debug('UserLookupRepository: Registro marcado como inativo', [
            'id' => $id,
        ]);
    }
    
    public function marcarComoAtivo(int $id): void
    {
        $model = UserLookupModel::findOrFail($id);
        $model->update(['status' => 'ativo']);
        
        Log::debug('UserLookupRepository: Registro marcado como ativo', [
            'id' => $id,
        ]);
    }
    
    public function buscarComFiltros(array $filtros = []): array
    {
        Log::info('UserLookupRepository::buscarComFiltros - Iniciando busca', [
            'filtros' => $filtros,
        ]);
        
        $query = UserLookupModel::query()
            ->whereNull('deleted_at');
        
        // Filtro de busca (email ou CNPJ)
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            Log::debug('UserLookupRepository::buscarComFiltros - Aplicando filtro de busca', [
                'search' => $search,
            ]);
            $query->where(function($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }
        
        // Filtro de status
        if (!empty($filtros['status']) && $filtros['status'] !== 'all') {
            Log::debug('UserLookupRepository::buscarComFiltros - Aplicando filtro de status', [
                'status' => $filtros['status'],
            ]);
            $query->where('status', $filtros['status']);
        }
        
        // Paginação
        $perPage = $filtros['per_page'] ?? 15;
        $page = $filtros['page'] ?? 1;
        
        $paginator = $query->orderBy('email')
            ->orderBy('tenant_id')
            ->paginate($perPage, ['*'], 'page', $page);
        
        $data = $paginator->items();
        $lookups = array_map(fn($model) => $this->toDomain($model), $data);
        
        // 🔥 LOG: Verificar se o email específico está nos resultados
        $emailEncontrado = false;
        foreach ($lookups as $lookup) {
            if (strtolower($lookup->email) === $emailProcuradoLower) {
                $emailEncontrado = true;
                Log::info('UserLookupRepository::buscarComFiltros - Email encontrado nos resultados', [
                    'email' => $lookup->email,
                    'tenant_id' => $lookup->tenantId,
                    'user_id' => $lookup->userId,
                    'status' => $lookup->status,
                ]);
                break;
            }
        }
        
        if (!$emailEncontrado && $existeNaQuery) {
            Log::warning('UserLookupRepository::buscarComFiltros - Email existe na query mas não está nos resultados (pode ser paginação)', [
                'email' => $emailProcurado,
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ]);
        }
        
        Log::info('UserLookupRepository::buscarComFiltros - Busca concluída', [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'resultados_count' => count($lookups),
            'email_encontrado' => $emailEncontrado,
        ]);
        
        return [
            'data' => $lookups,
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}




