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

        // 🔥 CRÍTICO: Verificar se estamos usando o banco de dados correto
        // O banco deve começar com 'tenant_' quando tenancy está inicializado
        $databaseName = \DB::connection()->getDatabaseName();
        $tenantId = tenancy()->tenant?->id;
        $expectedDatabaseName = 'tenant_' . $tenantId;
        
        if ($databaseName !== $expectedDatabaseName && !str_starts_with($databaseName, 'tenant_')) {
            \Log::error('UserReadRepository: Banco de dados incorreto!', [
                'database_name_atual' => $databaseName,
                'database_name_esperado' => $expectedDatabaseName,
                'tenant_id' => $tenantId,
                'tenancy_initialized' => tenancy()->initialized,
            ]);
            throw new \RuntimeException("Banco de dados incorreto. Esperado: {$expectedDatabaseName}, Atual: {$databaseName}");
        }

        // Carregar todos os relacionamentos necessários
        // IMPORTANTE: Incluir usuários deletados (soft deletes) para mostrar na listagem admin
        $query = UserModel::withTrashed()->with(['empresas', 'roles']);
        
        \Log::info('UserReadRepository: Listando usuários', [
            'filtros' => $filtros,
            'tenant_id' => $tenantId,
            'tenant_razao_social' => tenancy()->tenant?->razao_social ?? 'N/A',
            'tenancy_initialized' => tenancy()->initialized,
            'database_connection' => \DB::connection()->getName(),
            'database_name' => $databaseName,
            'database_name_esperado' => $expectedDatabaseName,
        ]);

        // 🔥 SEGURANÇA: Garantir que apenas usuários do tenant atual sejam listados
        // Como User não tem tenant_id direto, filtramos via relacionamento com Empresa
        // IMPORTANTE: Quando tenancy está inicializado, já estamos no banco do tenant (tenant_XX),
        // então todas as empresas já estão automaticamente filtradas pelo tenant.
        // O `whereHas('empresas')` garante que apenas usuários que têm pelo menos uma empresa sejam retornados,
        // e como estamos no banco do tenant, essas empresas são do tenant correto.
        
        // 🔥 UX: Filtrar por empresa específica quando solicitado
        // Comportamento:
        // - Se empresa_id for fornecido: mostrar APENAS usuários vinculados àquela empresa específica
        // - Se não for fornecido: mostrar TODOS os usuários do tenant (todas as empresas do tenant)
        if (isset($filtros['empresa_id']) && $filtros['empresa_id'] > 0) {
            \Log::info('UserReadRepository: Filtrando por empresa_id específico', [
                'empresa_id' => $filtros['empresa_id'],
                'tenant_id' => $tenantId,
                'database_name' => $databaseName,
            ]);
            // Filtrar apenas usuários que têm vínculo com a empresa específica
            $query->whereHas('empresas', function($q) use ($filtros) {
                $q->where('empresas.id', $filtros['empresa_id'])
                  ->whereNull('empresas.excluido_em');
            });
        } else {
            \Log::info('UserReadRepository: Mostrando TODOS os usuários do tenant (sem filtro de empresa)', [
                'tenant_id' => $tenantId,
                'tenancy_initialized' => tenancy()->initialized,
                'database_name' => $databaseName,
            ]);
            // Sem filtro de empresa_id, mostra todos os usuários que têm pelo menos uma empresa não deletada no tenant atual
            // Como estamos no banco do tenant (tenant_XX), todas as empresas aqui são do tenant correto
            // IMPORTANTE: O whereHas garante que apenas usuários com empresas sejam retornados
            $query->whereHas('empresas', function($q) {
                $q->whereNull('empresas.excluido_em');
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
        // 🔥 PERFORMANCE: Relacionamentos já estão carregados via with(['empresas', 'roles'])
        // Não precisa verificar ou carregar novamente
        $items = $paginator->getCollection()->map(function ($user) {
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

