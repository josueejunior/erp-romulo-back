<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read Repository: Apenas para leitura e apresentação
 * Conhece Eloquent, mas controller não conhece
 */
class UserReadRepository implements UserReadRepositoryInterface
{
    public function buscarComRelacionamentos(int $userId): ?array
    {
        $user = UserModel::with(['empresas', 'roles'])->find($userId);
        
        if (!$user) {
            return null;
        }

        // Garantir que empresas seja sempre um array
        $empresas = $user->empresas->map(fn($e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
        ])->toArray();

        // Garantir que roles seja sempre um array
        $roles = $user->roles->pluck('name')->toArray();

        // Buscar empresa ativa se existir
        $empresaAtiva = null;
        if ($user->empresa_ativa_id) {
            $empresaAtivaModel = $user->empresas->firstWhere('id', $user->empresa_ativa_id);
            if ($empresaAtivaModel) {
                $empresaAtiva = [
                    'id' => $empresaAtivaModel->id,
                    'razao_social' => $empresaAtivaModel->razao_social,
                ];
            }
        }

        // Calcular total de empresas para tag de multi-vínculo
        $totalEmpresas = count($empresas);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'empresa_ativa_id' => $user->empresa_ativa_id,
            'empresa_ativa' => $empresaAtiva,
            'roles' => $roles,
            'roles_list' => $roles, // Frontend espera isso também
            'empresas' => $empresas, // Garantir que seja array
            'empresas_list' => $empresas, // Frontend espera isso também
            'total_empresas' => $totalEmpresas, // 🔥 Tag de multi-vínculo: +2 empresas
            'is_multi_empresa' => $totalEmpresas > 1, // Flag para facilitar no frontend
        ];
    }

    /**
     * Buscar usuário por email
     * Usado para vincular usuário existente a uma nova empresa
     */
    public function buscarPorEmail(string $email): ?array
    {
        $user = UserModel::with(['empresas', 'roles'])->where('email', $email)->first();
        
        if (!$user) {
            return null;
        }

        // Reutilizar lógica do buscarComRelacionamentos
        $empresas = $user->empresas->map(fn($e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
        ])->toArray();

        $roles = $user->roles->pluck('name')->toArray();

        $empresaAtiva = null;
        if ($user->empresa_ativa_id) {
            $empresaAtivaModel = $user->empresas->firstWhere('id', $user->empresa_ativa_id);
            if ($empresaAtivaModel) {
                $empresaAtiva = [
                    'id' => $empresaAtivaModel->id,
                    'razao_social' => $empresaAtivaModel->razao_social,
                ];
            }
        }

        $totalEmpresas = count($empresas);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'empresa_ativa_id' => $user->empresa_ativa_id,
            'empresa_ativa' => $empresaAtiva,
            'roles' => $roles,
            'roles_list' => $roles,
            'empresas' => $empresas,
            'empresas_list' => $empresas,
            'total_empresas' => $totalEmpresas,
            'is_multi_empresa' => $totalEmpresas > 1,
        ];
    }

    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator
    {
        // Carregar todos os relacionamentos necessários
        // IMPORTANTE: Incluir usuários deletados (soft deletes) para mostrar na listagem admin
        $query = UserModel::withTrashed()->with(['empresas', 'roles']);

        \Log::info('UserReadRepository: Listando usuários', [
            'filtros' => $filtros,
            'tenant_id' => tenancy()->tenant?->id,
            'tenant_razao_social' => tenancy()->tenant?->razao_social ?? 'N/A',
            'tenancy_initialized' => tenancy()->initialized,
        ]);

        // 🔥 UX: Filtrar por empresa específica quando solicitado
        // Mostra APENAS usuários vinculados àquela empresa
        if (isset($filtros['empresa_id']) && $filtros['empresa_id']) {
            $query->whereHas('empresas', function($q) use ($filtros) {
                $q->where('empresas.id', $filtros['empresa_id']);
            });
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        
        // Log antes da query
        \Log::info('UserReadRepository: Executando query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);
        
        $paginator = $query->orderBy('name')->paginate($perPage);
        
        // Log após query
        \Log::info('UserReadRepository: Query executada', [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'items_count' => $paginator->getCollection()->count(),
        ]);

        // Transformar Collection para array
        // IMPORTANTE: Incluir todos os campos que o frontend espera
        $items = $paginator->getCollection()->map(function ($user) {
            // Carregar relacionamentos se não estiverem carregados
            if (!$user->relationLoaded('roles')) {
                $user->load('roles');
            }
            if (!$user->relationLoaded('empresas')) {
                $user->load('empresas');
            }
            
            // Calcular total de empresas para tag de multi-vínculo
            $totalEmpresas = $user->empresas->count();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_ativa_id' => $user->empresa_ativa_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'roles_list' => $user->roles->pluck('name')->toArray(), // Frontend espera isso
                'empresas' => $user->empresas->map(fn($e) => ['id' => $e->id, 'razao_social' => $e->razao_social])->toArray(),
                'empresas_list' => $user->empresas->map(fn($e) => ['id' => $e->id, 'razao_social' => $e->razao_social])->toArray(), // Frontend espera isso
                'total_empresas' => $totalEmpresas, // 🔥 Tag de multi-vínculo: +2 empresas
                'is_multi_empresa' => $totalEmpresas > 1, // Flag para facilitar no frontend
                'deleted_at' => $user->deleted_at?->toISOString() ?? null,
            ];
        })->values()->toArray();

        // Criar novo paginator com array (não Collection)
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => $paginator->path(),
                'pageName' => $paginator->getPageName(),
            ]
        );
    }
}

