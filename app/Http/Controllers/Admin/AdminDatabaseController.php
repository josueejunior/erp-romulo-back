<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminDatabaseController
 *
 * Tela administrativa para explorar bancos de dados (estilo DBeaver, mas controlado).
 *
 * Focado em VISUALIZAÇÃO (read-only):
 * - Lista tabelas do banco central ou de um tenant específico
 * - Lista colunas de uma tabela
 * - Lista linhas de uma tabela (paginado, com limite de registros)
 *
 * Segurança:
 * - Apenas usuários admin (rota dentro de /api/admin, protegida por middleware admin)
 * - Não executa SQL arbitrário
 * - Tabelas e nomes são validados para evitar SQL injection
 */
class AdminDatabaseController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Lista tabelas disponíveis em um contexto.
     *
     * GET /api/admin/db/tables?scope=central|tenant&tenant_id=4
     */
    public function listTables(Request $request)
    {
        $scope = $request->query('scope', 'central'); // central | tenant
        $tenantId = (int) $request->query('tenant_id', 0);

        try {
            if ($scope === 'tenant') {
                if (!$tenantId) {
                    return ApiResponse::error('tenant_id é obrigatório quando scope=tenant.', 400);
                }

                $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                if (!$tenantDomain) {
                    return ApiResponse::error('Tenant não encontrado.', 404);
                }

                $result = $this->adminTenancyRunner->runForTenant($tenantDomain, function () {
                    $databaseName = DB::connection('tenant')->getDatabaseName();

                    $tables = DB::connection('tenant')->select("
                        SELECT table_name
                        FROM information_schema.tables
                        WHERE table_schema = 'public'
                        ORDER BY table_name ASC
                    ");

                    return [
                        'connection' => 'tenant',
                        'database' => $databaseName,
                        'tables' => array_map(fn ($row) => $row->table_name, $tables),
                    ];
                });

                return ApiResponse::success('Tabelas do tenant carregadas com sucesso.', $result);
            }

            // Escopo CENTRAL (banco erp_licitacoes)
            $centralConnection = config('tenancy.database.central_connection', config('database.default', 'pgsql'));
            $databaseName = DB::connection($centralConnection)->getDatabaseName();

            $tables = DB::connection($centralConnection)->select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY table_name ASC
            ");

            $result = [
                'connection' => $centralConnection,
                'database' => $databaseName,
                'tables' => array_map(fn ($row) => $row->table_name, $tables),
            ];

            return ApiResponse::success('Tabelas do banco central carregadas com sucesso.', $result);
        } catch (\Exception $e) {
            Log::error('AdminDatabaseController::listTables - Erro ao listar tabelas', [
                'scope' => $scope,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao listar tabelas.', 500);
        }
    }

    /**
     * Lista colunas de uma tabela.
     *
     * GET /api/admin/db/tables/{table}/columns?scope=central|tenant&tenant_id=4
     */
    public function listColumns(Request $request, string $table)
    {
        $scope = $request->query('scope', 'central'); // central | tenant
        $tenantId = (int) $request->query('tenant_id', 0);

        // Validação simples do nome da tabela para evitar SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ApiResponse::error('Nome de tabela inválido.', 400);
        }

        try {
            $query = "
                SELECT 
                    column_name,
                    data_type,
                    is_nullable,
                    character_maximum_length,
                    column_default
                FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = ?
                ORDER BY ordinal_position
            ";

            if ($scope === 'tenant') {
                if (!$tenantId) {
                    return ApiResponse::error('tenant_id é obrigatório quando scope=tenant.', 400);
                }

                $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                if (!$tenantDomain) {
                    return ApiResponse::error('Tenant não encontrado.', 404);
                }

                $result = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($query, $table) {
                    $databaseName = DB::connection('tenant')->getDatabaseName();
                    $columns = DB::connection('tenant')->select($query, [$table]);

                    return [
                        'connection' => 'tenant',
                        'database' => $databaseName,
                        'table' => $table,
                        'columns' => $columns,
                    ];
                });

                return ApiResponse::success('Colunas da tabela carregadas com sucesso.', $result);
            }

            $centralConnection = config('tenancy.database.central_connection', config('database.default', 'pgsql'));
            $databaseName = DB::connection($centralConnection)->getDatabaseName();
            $columns = DB::connection($centralConnection)->select($query, [$table]);

            $result = [
                'connection' => $centralConnection,
                'database' => $databaseName,
                'table' => $table,
                'columns' => $columns,
            ];

            return ApiResponse::success('Colunas da tabela carregadas com sucesso.', $result);
        } catch (\Exception $e) {
            Log::error('AdminDatabaseController::listColumns - Erro ao listar colunas', [
                'scope' => $scope,
                'tenant_id' => $tenantId,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao listar colunas da tabela.', 500);
        }
    }

    /**
     * Lista linhas de uma tabela (read-only, paginado).
     *
     * GET /api/admin/db/tables/{table}/rows?scope=central|tenant&tenant_id=4&page=1&per_page=50
     */
    public function listRows(Request $request, string $table)
    {
        $scope = $request->query('scope', 'central'); // central | tenant
        $tenantId = (int) $request->query('tenant_id', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200)); // limitar para evitar consultas muito pesadas

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ApiResponse::error('Nome de tabela inválido.', 400);
        }

        try {
            $offset = ($page - 1) * $perPage;

            if ($scope === 'tenant') {
                if (!$tenantId) {
                    return ApiResponse::error('tenant_id é obrigatório quando scope=tenant.', 400);
                }

                $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                if (!$tenantDomain) {
                    return ApiResponse::error('Tenant não encontrado.', 404);
                }

                $result = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($table, $perPage, $offset) {
                    $databaseName = DB::connection('tenant')->getDatabaseName();

                    // Contagem total
                    $total = DB::connection('tenant')->table($table)->count();

                    // Linhas (sem filtros inicialmente)
                    $rows = DB::connection('tenant')->table($table)
                        ->offset($offset)
                        ->limit($perPage)
                        ->get();

                    return [
                        'connection' => 'tenant',
                        'database' => $databaseName,
                        'table' => $table,
                        'data' => $rows,
                        'pagination' => [
                            'total' => $total,
                            'per_page' => $perPage,
                            'current_page' => $page,
                            'last_page' => (int) ceil($total / $perPage),
                        ],
                    ];
                });

                return ApiResponse::success('Linhas da tabela carregadas com sucesso.', $result);
            }

            $centralConnection = config('tenancy.database.central_connection', config('database.default', 'pgsql'));
            $databaseName = DB::connection($centralConnection)->getDatabaseName();

            $total = DB::connection($centralConnection)->table($table)->count();

            $rows = DB::connection($centralConnection)->table($table)
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $result = [
                'connection' => $centralConnection,
                'database' => $databaseName,
                'table' => $table,
                'data' => $rows,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ];

            return ApiResponse::success('Linhas da tabela carregadas com sucesso.', $result);
        } catch (\Exception $e) {
            Log::error('AdminDatabaseController::listRows - Erro ao listar linhas', [
                'scope' => $scope,
                'tenant_id' => $tenantId,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao listar linhas da tabela.', 500);
        }
    }
}

