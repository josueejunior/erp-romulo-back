<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserReadRepository implements UserReadRepositoryInterface
{
    public function buscarComRelacionamentos(int $userId): ?array
    {
        $this->checkTenancyContext();
        
        try {
            $user = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->find($userId);
            return $user ? $this->mapUserToArray($user) : null;
        } catch (\Exception $e) {
            Log::error("Erro ao buscar usuário por ID: " . $e->getMessage(), [
                'user_id' => $userId,
                'tenant_id' => tenancy()->tenant?->id,
            ]);
            return null;
        }
    }

    public function buscarPorEmail(string $email): ?array
    {
        $this->checkTenancyContext();
        
        try {
            $user = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->where('email', $email)
                ->first();
            return $user ? $this->mapUserToArray($user) : null;
        } catch (\Exception $e) {
            Log::error("Erro ao buscar usuário por email: " . $e->getMessage(), [
                'email' => $email,
                'tenant_id' => tenancy()->tenant?->id,
            ]);
            return null;
        }
    }

    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator
    {
        $this->checkTenancyContext();

        try {
            $query = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                    $search = $filtros['search'];
                    $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")
                                              ->orWhere('email', 'like', "%{$search}%"));
                })
                ->when(!empty($filtros['empresa_id']), function ($q) use ($filtros) {
                    $q->whereHas('empresas', fn($e) => $e->where('empresas.id', $filtros['empresa_id']));
                });

            $paginator = $query->orderBy('name')->paginate($filtros['per_page'] ?? 15);

            // Transforma os itens usando o método map que já criamos
            $paginator->setCollection(
                $paginator->getCollection()->map(fn($user) => $this->mapUserToArray($user))
            );

            return $paginator;

        } catch (\Exception $e) {
            Log::error("Erro ao listar usuários: " . $e->getMessage(), [
                'tenant_id' => tenancy()->tenant?->id,
                'filtros' => $filtros,
            ]);
            return $this->createEmptyPaginator($filtros);
        }
    }

    public function listarSemPaginacao(array $filtros = []): array
    {
        $this->checkTenancyContext();
        
        $tenantId = tenancy()->tenant?->id;
        $databaseName = DB::connection()->getDatabaseName();
        
        Log::info('UserReadRepository::listarSemPaginacao - Iniciando', [
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'filtros' => $filtros,
        ]);

        try {
            $query = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                    $search = $filtros['search'];
                    $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")
                                              ->orWhere('email', 'like', "%{$search}%"));
                })
                ->when(!empty($filtros['empresa_id']), function ($q) use ($filtros) {
                    $q->whereHas('empresas', fn($e) => $e->where('empresas.id', $filtros['empresa_id']));
                });

            $users = $query->orderBy('name')->get();
            
            Log::info('UserReadRepository::listarSemPaginacao - Usuários encontrados', [
                'total_usuarios' => $users->count(),
                'tenant_id' => $tenantId,
                'database' => $databaseName,
            ]);

            // Transforma os itens usando o método map que já criamos
            $result = $users->map(fn($user) => $this->mapUserToArray($user))->toArray();
            
            Log::info('UserReadRepository::listarSemPaginacao - Concluído', [
                'total_resultados' => count($result),
                'tenant_id' => $tenantId,
            ]);
            
            return $result;

        } catch (\Exception $e) {
            Log::error("Erro ao listar usuários sem paginação: " . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'database' => $databaseName,
                'filtros' => $filtros,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Centraliza a transformação do Model para o Array de saída (Frontend)
     */
    private function mapUserToArray(UserModel $user): array
    {
        $empresas = $user->empresas->map(fn($e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
        ])->toArray();

        $roles = $user->roles->pluck('name')->toArray();
        $totalEmpresas = count($empresas);
        
        $empresaAtiva = null;
        if ($user->empresa_ativa_id) {
            $modelAtiva = $user->empresas->firstWhere('id', $user->empresa_ativa_id);
            $empresaAtiva = $modelAtiva ? [
                'id' => $modelAtiva->id,
                'razao_social' => $modelAtiva->razao_social,
            ] : null;
        }

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
            'deleted_at' => ($user->trashed() && ($deletedAt = $user->getAttribute($user->getDeletedAtColumn()))) 
                ? $deletedAt->toISOString() 
                : null,
        ];
    }

    /**
     * Centraliza a query de usuários com isolamento de tenant
     * 
     * 🔥 SEGURANÇA: Garante que toda query de usuário nasça com o filtro de Tenant,
     * mesmo que o Laravel falhe em trocar a conexão. Se estivermos no banco central
     * (fallback quando o banco tenant não existe), FORÇA join com empresas do tenant
     * para garantir que dados não vazem entre tenants.
     * 
     * Nota: O Global Scope no Model User também aplica este filtro como camada adicional
     * de segurança. Esta é uma implementação de "defesa em profundidade".
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getIsolatedUserQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = tenancy()->tenant?->id;
        
        // 1. Tenta obter a conexão correta
        $query = UserModel::withTrashed();

        // 2. Segurança: Se estivermos no banco central, FORÇAR join com empresas do tenant
        // Isso garante que mesmo se a conexão 'tenant' falhar, os dados não vazem
        $databaseName = DB::connection()->getDatabaseName();
        
        if (!str_starts_with($databaseName, 'tenant_')) {
            // Estamos no banco central (fallback) - aplicar filtro de segurança
            // Filtrar empresas que pertencem ao tenant através da tabela tenant_empresas
            if ($tenantId) {
                // Buscar empresa_ids do tenant através da tabela tenant_empresas (banco central)
                $empresaIds = \App\Models\TenantEmpresa::where('tenant_id', $tenantId)
                    ->pluck('empresa_id')
                    ->toArray();
                
                if (!empty($empresaIds)) {
                    // Filtrar usuários que têm relacionamento com empresas do tenant
                    $query->whereHas('empresas', function ($q) use ($empresaIds) {
                        $q->whereIn('empresas.id', $empresaIds);
                    });
                } else {
                    // Se não houver empresas mapeadas, não retornar nenhum usuário
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Sem tenant_id, não retornar nenhum usuário por segurança
                $query->whereRaw('1 = 0');
            }
        }
        // Se estiver no banco tenant (str_starts_with($databaseName, 'tenant_')),
        // a query já está isolada naturalmente pelo banco de dados

        return $query;
    }
    
    /**
     * Cria um paginador vazio quando não há dados disponíveis
     */
    private function createEmptyPaginator(array $filtros): LengthAwarePaginator
    {
        $perPage = $filtros['per_page'] ?? 15;
        $currentPage = request()->get('page', 1);
        
        return new Paginator(
            [],
            0,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Valida se o contexto do tenancy está inicializado
     */
    private function checkTenancyContext(): void
    {
        if (!tenancy()->initialized) {
            Log::error('UserReadRepository: Acesso tentado sem inicializar Tenancy.');
            throw new \RuntimeException('Contexto de Tenant não identificado.');
        }
    }
}

