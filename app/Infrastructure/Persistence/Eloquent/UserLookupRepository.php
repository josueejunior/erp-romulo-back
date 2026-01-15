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
        
        // 🔥 SOLUÇÃO PROFUNDA: Usar updateOrCreate para garantir idempotência
        // A tabela tem duas constraints únicas:
        // 1. (email, tenant_id) - users_lookup_email_tenant_unique
        // 2. (cnpj, tenant_id) - users_lookup_cnpj_tenant_unique
        // 
        // Como o erro ocorre na constraint (cnpj, tenant_id), vamos usar ela como chave de busca
        // Se já existir um registro com mesmo CNPJ+tenant, atualizamos; caso contrário, criamos.
        //
        // ⚠️ IMPORTANTE: Isso garante que não haverá Unique Violation e a transação não será abortada
        // 
        // 🔥 CORREÇÃO: Buscar incluindo soft deleted para restaurar se necessário
        $model = UserLookupModel::withTrashed()->updateOrCreate(
            [
                'cnpj' => $data['cnpj'],
                'tenant_id' => $data['tenant_id'],
            ],
            [
                'email' => $data['email'],
                'user_id' => $data['user_id'],
                'empresa_id' => $data['empresa_id'],
                'status' => $data['status'] ?? 'ativo',
                'deleted_at' => null, // Garantir que não está soft deleted
            ]
        );
        
        // Se o registro estava soft deleted, garantir que foi restaurado
        if ($model->trashed()) {
            $model->restore();
        }
        
        // Refresh para garantir que temos os dados mais recentes
        $model->refresh();
        
        Log::debug('UserLookupRepository: Registro processado (UPSERT)', [
            'id' => $model->id,
            'was_recently_created' => $model->wasRecentlyCreated ?? false,
            'email' => $model->email,
            'cnpj' => $model->cnpj,
            'tenant_id' => $model->tenant_id,
            'user_id' => $model->user_id,
            'empresa_id' => $model->empresa_id,
            'status' => $model->status,
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
        
        // ✅ DEBUG: Contar total de registros antes de aplicar filtros
        $totalSemFiltros = UserLookupModel::whereNull('deleted_at')->count();
        Log::info('UserLookupRepository::buscarComFiltros - Total de registros na tabela (sem filtros)', [
            'total' => $totalSemFiltros,
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
            
            // ✅ DEBUG: Contar registros com o status antes de aplicar filtro
            $totalComStatus = UserLookupModel::whereNull('deleted_at')
                ->where('status', $filtros['status'])
                ->count();
            Log::info('UserLookupRepository::buscarComFiltros - Total de registros com status', [
                'status' => $filtros['status'],
                'total' => $totalComStatus,
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
        
        Log::info('UserLookupRepository::buscarComFiltros - Busca concluída', [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'resultados_count' => count($lookups),
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




