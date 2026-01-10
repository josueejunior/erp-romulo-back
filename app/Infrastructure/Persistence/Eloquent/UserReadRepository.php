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
        // 🔥 CRÍTICO: Verificar se tenancy está inicializado
        // Se não estiver, não devemos fazer queries pois não sabemos qual tenant usar
        if (!tenancy()->initialized) {
            \Log::error('UserReadRepository: Tenancy não inicializado!', [
                'filtros' => $filtros,
            ]);
            throw new \RuntimeException('Tenancy não inicializado. Não é possível listar usuários sem contexto de tenant.');
        }

        // Obter informações do tenant e banco de dados
        $tenantId = tenancy()->tenant?->id;
        $databaseName = \DB::connection()->getDatabaseName();
        $connectionName = \DB::connection()->getName();
        
        \Log::info('UserReadRepository: Listando usuários', [
            'filtros' => $filtros,
            'tenant_id' => $tenantId,
            'tenant_razao_social' => tenancy()->tenant?->razao_social ?? 'N/A',
            'tenancy_initialized' => tenancy()->initialized,
            'database_connection' => $connectionName,
            'database_name' => $databaseName,
        ]);

        // 🔥 CRÍTICO: Quando tenancy está inicializado, o Laravel muda automaticamente a conexão do banco
        // para o banco do tenant (ex: tenant_2). Isso significa que:
        // - A tabela `users` já está no banco do tenant
        // - A tabela `empresas` já está no banco do tenant  
        // - A tabela `empresa_user` (pivot) já está no banco do tenant
        // Então, todas as queries já estão automaticamente no contexto correto.
        //
        // IMPORTANTE: Usar whereHas ao invés de JOIN direto, pois o JOIN pode causar problemas
        // com eager loading. O whereHas gera uma subquery EXISTS que é mais segura.
        
        // 🔥 CRÍTICO: Garantir que apenas usuários do tenant atual sejam listados
        // IMPORTANTE: Quando tenancy está inicializado, o Laravel muda automaticamente a conexão do banco
        // para o banco do tenant (ex: tenant_2). Isso significa que:
        // - A tabela `users` já está no banco do tenant (tenant_2)
        // - A tabela `empresas` já está no banco do tenant (tenant_2)
        // - A tabela `empresa_user` (pivot) já está no banco do tenant (tenant_2)
        // Então, TODAS as queries já estão automaticamente no contexto correto do tenant.
        //
        // IMPORTANTE: Usar JOIN direto para garantir que apenas usuários com empresas válidas sejam retornados.
        // O JOIN é mais explícito e eficiente do que whereHas para este caso.
        
        // 🔥 CRÍTICO: Forçar a query a usar a conexão 'tenant' quando tenancy estiver inicializado
        // O stancl/tenancy cria uma conexão dinâmica chamada 'tenant' quando initialize() é chamado
        // Mas os modelos Eloquent ainda usam a conexão padrão, então precisamos forçar explicitamente
        $useTenantConnection = false;
        $tenantConnection = null;
        if (tenancy()->initialized) {
            try {
                // Verificar se a conexão 'tenant' existe (criada pelo DatabaseTenancyBootstrapper)
                $tenantConnection = \DB::connection('tenant');
                $tenantDbName = $tenantConnection->getDatabaseName();
                $useTenantConnection = true;
                
                \Log::debug('UserReadRepository: Usando conexão tenant', [
                    'tenant_db_name' => $tenantDbName,
                    'tenant_id' => $tenantId,
                ]);
            } catch (\Exception $e) {
                \Log::warning('UserReadRepository: Conexão "tenant" não encontrada, usando conexão padrão do modelo', [
                    'error' => $e->getMessage(),
                    'tenant_id' => $tenantId,
                ]);
                // Se a conexão tenant não existir, usar a conexão padrão do modelo
                $useTenantConnection = false;
            }
        }
        
        // Carregar todos os relacionamentos necessários
        // IMPORTANTE: Incluir usuários deletados (soft deletes) para mostrar na listagem admin
        // 🔥 CRÍTICO: Usar JOIN direto para garantir que apenas usuários com empresas sejam retornados
        // Isso garante que estamos realmente no banco do tenant e apenas usuários válidos são retornados
        
        // 🔥 CRÍTICO: Criar uma nova instância do modelo com a conexão tenant se necessário
        if ($useTenantConnection && $tenantConnection) {
            // Criar uma nova instância do modelo configurada com a conexão tenant
            $userInstance = (new UserModel())->setConnection('tenant');
            $query = $userInstance->newQuery()->withTrashed();
        } else {
            // Usar a conexão padrão do modelo
            $query = UserModel::withTrashed();
        }
        
        $query = $query
            ->join('empresa_user', 'users.id', '=', 'empresa_user.user_id')
            ->join('empresas', function($join) use ($filtros) {
                $join->on('empresa_user.empresa_id', '=', 'empresas.id')
                     ->whereNull('empresas.excluido_em');
                // Se empresa_id for especificado, adicionar filtro aqui
                if (isset($filtros['empresa_id']) && $filtros['empresa_id'] > 0) {
                    $join->where('empresas.id', $filtros['empresa_id']);
                }
            })
            ->select('users.*') // Selecionar apenas colunas da tabela users para evitar ambiguidade
            ->distinct() // Garantir que não há duplicatas devido ao JOIN múltiplo
            ->with(['empresas', 'roles']); // Eager loading dos relacionamentos (após JOIN)
        
        // 🔥 UX: Log do comportamento
        if (isset($filtros['empresa_id']) && $filtros['empresa_id'] > 0) {
            \Log::info('UserReadRepository: Filtrando por empresa_id específico', [
                'empresa_id' => $filtros['empresa_id'],
                'tenant_id' => $tenantId,
                'database_name' => $databaseName,
            ]);
        } else {
            \Log::info('UserReadRepository: Mostrando TODOS os usuários do tenant (sem filtro de empresa)', [
                'tenant_id' => $tenantId,
                'tenancy_initialized' => tenancy()->initialized,
                'database_name' => $databaseName,
            ]);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        
        // Obter informações da conexão que o modelo está usando
        $modelConnection = UserModel::getConnection();
        $currentDatabaseName = $modelConnection->getDatabaseName();
        $connectionName = $modelConnection->getName();
        $expectedDatabaseName = 'tenant_' . $tenantId;
        
        \Log::info('UserReadRepository: Verificando banco de dados antes da query', [
            'current_database_name' => $currentDatabaseName,
            'expected_database_name' => $expectedDatabaseName,
            'tenant_id' => $tenantId,
            'tenancy_initialized' => tenancy()->initialized,
            'database_connection' => $connectionName,
            'connection_used' => $connectionName === 'tenant' ? 'tenant (correto)' : 'padrão (' . $connectionName . ')',
        ]);
        
        // 🔥 CRÍTICO: Verificar se o banco está correto (deve começar com 'tenant_')
        // Se tenancy está inicializado mas estamos usando banco central, há um problema de configuração
        if (tenancy()->initialized && !str_starts_with($currentDatabaseName, 'tenant_')) {
            \Log::error('UserReadRepository: Banco de dados incorreto! Tenancy inicializado mas usando banco central', [
                'current_database_name' => $currentDatabaseName,
                'expected_database_name' => $expectedDatabaseName,
                'tenant_id' => $tenantId,
                'tenancy_initialized' => tenancy()->initialized,
                'database_connection' => $connectionName,
            ]);
            throw new \RuntimeException("Banco de dados incorreto. Tenancy está inicializado mas o modelo está usando banco central ({$currentDatabaseName}). Esperado banco do tenant ({$expectedDatabaseName}). Verifique se o DatabaseTenancyBootstrapper está funcionando corretamente.");
        }
        
        // Log antes da query - verificar SQL completo com subqueries
        \Log::info('UserReadRepository: Executando query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'database_name' => $currentDatabaseName,
            'database_connection' => \DB::connection()->getName(),
            'tenant_id' => $tenantId,
            'tenant_razao_social' => tenancy()->tenant?->razao_social ?? 'N/A',
        ]);
        
        // Executar query e obter resultados
        $paginator = $query->orderBy('name')->paginate($perPage);
        
        // 🔥 CRÍTICO: Verificar se os usuários retornados realmente pertencem ao tenant correto
        // Filtrar no PHP para garantir que apenas usuários com empresas do tenant atual sejam retornados
        $items = $paginator->getCollection()->filter(function ($user) use ($tenantId) {
            // Verificar se o usuário tem pelo menos uma empresa não deletada
            $hasValidEmpresa = $user->empresas->whereNull('excluido_em')->count() > 0;
            
            if (!$hasValidEmpresa) {
                \Log::warning('UserReadRepository: Usuário sem empresa válida filtrado', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'tenant_id' => $tenantId,
                ]);
                return false;
            }
            
            return true;
        });
        
        // Atualizar total após filtro
        $totalFiltered = $items->count();
        
        \Log::info('UserReadRepository: Filtro aplicado', [
            'total_antes_filtro' => $paginator->count(),
            'total_apos_filtro' => $totalFiltered,
            'tenant_id' => $tenantId,
        ]);
        
        // Log após query
        \Log::info('UserReadRepository: Query executada', [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'items_count' => $paginator->getCollection()->count(),
            'database_name' => \DB::connection()->getDatabaseName(),
        ]);

        // Transformar Collection filtrada para array
        // IMPORTANTE: Incluir todos os campos que o frontend espera
        // 🔥 PERFORMANCE: Relacionamentos já estão carregados via with(['empresas', 'roles'])
        // Não precisa verificar ou carregar novamente
        $items = $items->map(function ($user) {
            // Relacionamentos já estão carregados via eager loading (with())
            // Não precisa verificar relationLoaded nem fazer load() adicional
            
            // Calcular total de empresas para tag de multi-vínculo
            $totalEmpresas = $user->empresas->count();
            $rolesArray = $user->roles->pluck('name')->toArray();
            $empresasArray = $user->empresas->map(fn($e) => ['id' => $e->id, 'razao_social' => $e->razao_social])->toArray();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_ativa_id' => $user->empresa_ativa_id,
                'roles' => $rolesArray,
                'roles_list' => $rolesArray, // Frontend espera isso
                'empresas' => $empresasArray,
                'empresas_list' => $empresasArray, // Frontend espera isso
                'total_empresas' => $totalEmpresas,
                'is_multi_empresa' => $totalEmpresas > 1, // Flag para facilitar no frontend
                // Usar getDeletedAtColumn() para acessar a coluna correta (excluido_em)
                'deleted_at' => $user->{$user->getDeletedAtColumn()}?->toISOString() ?? null,
            ];
        })->values()->toArray();

        // Criar novo paginator com array filtrado (não Collection)
        // NOTA: $items já é um array após o map()->values()->toArray()
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items, // Já é um array
            $totalFiltered, // Usar total filtrado ao invés do total original
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => $paginator->path(),
                'pageName' => $paginator->getPageName(),
            ]
        );
    }
}

