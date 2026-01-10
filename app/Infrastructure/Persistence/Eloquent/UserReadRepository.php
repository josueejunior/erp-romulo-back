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
        $user = UserModel::with(['empresas', 'roles'])->find($userId);
        return $user ? $this->mapUserToArray($user) : null;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $user = UserModel::with(['empresas', 'roles'])->where('email', $email)->first();
        return $user ? $this->mapUserToArray($user) : null;
    }

    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator
    {
        $this->checkTenancyContext();

        // 🔥 CRÍTICO: Garantir que o modelo use a conexão 'tenant' quando disponível
        // O DatabaseTenancyBootstrapper deveria fazer isso automaticamente, mas se não estiver
        // funcionando, precisamos forçar explicitamente para garantir isolamento de dados
        $query = $this->getUserQuery();
        
        $query = $query
            ->with(['empresas', 'roles'])
            // Filtra para garantir que o usuário pertence a pelo menos uma empresa no tenant atual
            ->whereHas('empresas', function ($q) use ($filtros) {
                $q->whereNull('empresas.excluido_em');
                if (!empty($filtros['empresa_id'])) {
                    $q->where('empresas.id', $filtros['empresa_id']);
                }
            });

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('name')->paginate($filtros['per_page'] ?? 15);

        // Transforma os itens mantendo a estrutura do paginador
        $items = collect($paginator->items())->map(fn($user) => $this->mapUserToArray($user));

        return new Paginator(
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
     * Obtém query builder do User usando a conexão correta
     * Garante que a conexão 'tenant' seja usada quando disponível
     */
    private function getUserQuery()
    {
        if (tenancy()->initialized) {
            try {
                // Verificar se a conexão 'tenant' existe e está configurada
                $tenantConnection = DB::connection('tenant');
                $dbName = $tenantConnection->getDatabaseName();
                
                Log::info('UserReadRepository: Usando conexão tenant', [
                    'connection' => 'tenant',
                    'database_name' => $dbName,
                    'tenant_id' => tenancy()->tenant?->id,
                ]);
                
                // Se a conexão existe, criar instância do modelo com essa conexão
                $userInstance = new UserModel();
                $userInstance->setConnection('tenant');
                return $userInstance->newQuery()->withTrashed();
            } catch (\Exception $e) {
                // Se não existir, usar conexão padrão (pode ser um problema de configuração)
                Log::warning('UserReadRepository: Conexão tenant não disponível, usando padrão', [
                    'error' => $e->getMessage(),
                    'default_connection' => DB::connection()->getName(),
                    'default_database' => DB::connection()->getDatabaseName(),
                ]);
            }
        }
        
        // Fallback: usar conexão padrão do modelo
        Log::info('UserReadRepository: Usando conexão padrão', [
            'connection' => DB::connection()->getName(),
            'database_name' => DB::connection()->getDatabaseName(),
            'tenancy_initialized' => tenancy()->initialized,
        ]);
        return UserModel::withTrashed();
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
