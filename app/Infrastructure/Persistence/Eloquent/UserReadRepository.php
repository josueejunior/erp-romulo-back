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

        try {
            // 🔥 CRÍTICO: Garantir que o modelo use a conexão 'tenant' quando disponível
            // O DatabaseTenancyBootstrapper deveria fazer isso automaticamente, mas se não estiver
            // funcionando, precisamos forçar explicitamente para garantir isolamento de dados
            $query = $this->getUserQuery();
            
            if (!$query) {
                // Se não foi possível obter query (banco não existe, etc), retornar lista vazia
                return $this->createEmptyPaginator($filtros);
            }
            
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
        } catch (\Illuminate\Database\QueryException $e) {
            // Erro de banco de dados (banco não existe, tabela não existe, etc)
            Log::warning('UserReadRepository: Erro ao listar usuários - banco ou tabela não existe', [
                'error' => $e->getMessage(),
                'tenant_id' => tenancy()->tenant?->id,
                'database' => tenancy()->tenant?->database()->getName() ?? 'N/A',
            ]);
            
            // Retornar lista vazia ao invés de quebrar
            return $this->createEmptyPaginator($filtros);
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
     * Obtém query builder do User usando a conexão correta
     * 🔥 CRÍTICO: Configura manualmente a conexão 'tenant' para usar o banco correto
     * O DatabaseTenancyBootstrapper deveria fazer isso, mas se não estiver funcionando,
     * configuramos manualmente para garantir isolamento de dados
     * 
     * @return \Illuminate\Database\Eloquent\Builder|null Retorna null se não for possível configurar a conexão
     */
    private function getUserQuery()
    {
        if (tenancy()->initialized && tenancy()->tenant) {
            try {
                $tenant = tenancy()->tenant;
                $expectedDbName = $tenant->database()->getName(); // Deveria ser 'tenant_2' por exemplo
                
                // Verificar se a conexão 'tenant' existe
                $tenantConnection = DB::connection('tenant');
                $currentDbName = $tenantConnection->getDatabaseName();
                
                // Se a conexão tenant está apontando para o banco errado, configurar corretamente
                if ($currentDbName !== $expectedDbName) {
                    Log::warning('UserReadRepository: Conexão tenant apontando para banco errado, reconfigurando', [
                        'current_database' => $currentDbName,
                        'expected_database' => $expectedDbName,
                        'tenant_id' => $tenant->id,
                    ]);
                    
                    // Reconfigurar a conexão tenant para usar o banco correto
                    config(["database.connections.tenant.database" => $expectedDbName]);
                    DB::purge('tenant'); // Limpar cache da conexão
                    
                    // Tentar reconectar e verificar se o banco existe
                    try {
                        $tenantConnection = DB::connection('tenant');
                        // Tentar executar uma query simples para verificar se o banco existe
                        $tenantConnection->select('SELECT 1');
                    } catch (\Exception $e) {
                        Log::warning('UserReadRepository: Banco tenant não existe ou não está acessível', [
                            'expected_database' => $expectedDbName,
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                        return null; // Banco não existe, retornar null
                    }
                    
                    Log::info('UserReadRepository: Conexão tenant reconfigurada', [
                        'connection' => 'tenant',
                        'database_name' => $tenantConnection->getDatabaseName(),
                        'tenant_id' => $tenant->id,
                    ]);
                } else {
                    // Verificar se o banco existe fazendo uma query simples
                    try {
                        $tenantConnection->select('SELECT 1');
                    } catch (\Exception $e) {
                        Log::warning('UserReadRepository: Banco tenant não existe ou não está acessível', [
                            'current_database' => $currentDbName,
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                        return null; // Banco não existe, retornar null
                    }
                    
                    Log::info('UserReadRepository: Conexão tenant configurada corretamente', [
                        'connection' => 'tenant',
                        'database_name' => $currentDbName,
                        'tenant_id' => $tenant->id,
                    ]);
                }
                
                // Criar instância do modelo com a conexão tenant configurada corretamente
                $userInstance = new UserModel();
                $userInstance->setConnection('tenant');
                return $userInstance->newQuery()->withTrashed();
            } catch (\Exception $e) {
                // Se houver erro, logar e retornar null
                Log::error('UserReadRepository: Erro ao configurar conexão tenant', [
                    'error' => $e->getMessage(),
                    'tenant_id' => tenancy()->tenant?->id,
                ]);
                return null;
            }
        }
        
        // Se tenancy não está inicializado, não devemos retornar query
        Log::error('UserReadRepository: Tentativa de obter query sem tenancy inicializado');
        return null;
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

