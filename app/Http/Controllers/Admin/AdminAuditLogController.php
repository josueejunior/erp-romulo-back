<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AdminAuditLog;
use App\Models\AuditLog;
use App\Support\Logging\AdminLogger;
use Illuminate\Http\Request;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;

/**
 * Listagem de logs de auditoria administrativa (admin_audit_logs).
 *
 * Banco CENTRAL (connection = pgsql), sem contexto de tenant.
 */
class AdminAuditLogController extends Controller
{
    use AdminLogger;

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * GET /api/admin/audit-logs
     *
     * Filtros suportados:
     * - admin_id      (quando lendo admin_audit_logs - central)
     * - action        (prefix match, ex: "user." traz user.created, user.updated...)
     * - resource_type
     * - resource_id
     * - tenant_id     (quando informado, lê audit_logs do banco do tenant)
     * - empresa_id    (reservado para futura correlação central)
     * - data_inicio (YYYY-MM-DD)
     * - data_fim    (YYYY-MM-DD)
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 20);
            $perPage = $perPage > 100 ? 100 : $perPage;

            $tenantId = (int) $request->input('tenant_id', 0);
            $page = max(1, (int) $request->input('page', 1));
            $allTenants = filter_var($request->input('all_tenants', false), FILTER_VALIDATE_BOOLEAN);

            /**
             * 🔹 Modo 1: Logs de domínio por tenant (tabela audit_logs no banco do tenant)
             * Quando tenant_id é informado, usamos AdminTenancyRunner para ler audit_logs
             * diretamente do banco do tenant e retornamos no mesmo formato da API.
             */
            if ($tenantId > 0) {
                $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                if (!$tenantDomain) {
                    return ApiResponse::error('Tenant não encontrado.', 404);
                }

                $result = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($request, $perPage, $tenantId) {
                    $query = AuditLog::query()->orderByDesc('criado_em');

                    if ($request->filled('action')) {
                        $action = $request->input('action');
                        $query->where('action', 'like', $action . '%');
                    }

                    if ($request->filled('resource_type')) {
                        $query->where('model_type', $request->input('resource_type'));
                    }

                    if ($request->filled('resource_id')) {
                        $query->where('model_id', (int) $request->input('resource_id'));
                    }

                    if ($request->filled('admin_id')) {
                        $query->where('usuario_id', (int) $request->input('admin_id'));
                    }

                    if ($request->filled('data_inicio')) {
                        $query->whereDate('criado_em', '>=', $request->input('data_inicio'));
                    }

                    if ($request->filled('data_fim')) {
                        $query->whereDate('criado_em', '<=', $request->input('data_fim'));
                    }

                    $paginado = $query->paginate($perPage);

                    // Mapear para o mesmo formato que o frontend espera
                    $items = collect($paginado->items())->map(function (AuditLog $log) use ($tenantId) {
                        return [
                            'id'            => $log->id,
                            'admin_id'      => $log->usuario_id, // usuário do tenant
                            'action'        => $log->action,
                            'resource_type' => $log->model_type,
                            'resource_id'   => $log->model_id,
                            'tenant_id'     => $tenantId,
                            'empresa_id'    => null, // reservado para futura correlação
                            'ip_address'    => $log->ip_address,
                            'user_agent'    => $log->user_agent,
                            'context'       => [
                                'old_values' => $log->old_values,
                                'new_values' => $log->new_values,
                                'changes'    => $log->changes,
                            ],
                            'created_at'    => $log->criado_em,
                            'updated_at'    => $log->atualizado_em,
                        ];
                    })->toArray();

                    return [
                        'items' => $items,
                        'total' => $paginado->total(),
                        'per_page' => $paginado->perPage(),
                        'current_page' => $paginado->currentPage(),
                        'last_page' => $paginado->lastPage(),
                    ];
                });

                $this->logAdminInfo('AdminAuditLogController::index - Listando logs de audit_logs do tenant', [
                    'tenant_id' => $tenantId,
                    'total' => $result['total'] ?? 0,
                    'page' => $result['current_page'] ?? null,
                    'per_page' => $result['per_page'] ?? null,
                ]);

                return ApiResponse::collection($result['items'] ?? [], [
                    'total' => $result['total'] ?? 0,
                    'per_page' => $result['per_page'] ?? $perPage,
                    'current_page' => $result['current_page'] ?? $page,
                    'last_page' => $result['last_page'] ?? 1,
                ]);
            }

            /**
             * 🔹 Modo 2: Agregar logs de TODOS os tenants (tabela audit_logs em cada banco de tenant)
             * Quando all_tenants=1 é informado (e nenhum tenant_id específico), percorremos todos
             * os tenants conhecidos, lemos seus audit_logs e unificamos em uma lista única.
             *
             * Obs.: também incluímos os registros de admin_audit_logs do banco central para
             * ter uma visão global.
             */
            if ($allTenants && $tenantId === 0) {
                $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
                $tenants = $tenantsPaginator->getCollection();

                $items = [];

                foreach ($tenants as $tenantDomain) {
                    try {
                        $tenantIdLoop = $tenantDomain->id;

                        $tenantItems = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($request, $tenantIdLoop) {
                            $query = AuditLog::query()->orderByDesc('criado_em');

                            if ($request->filled('action')) {
                                $action = $request->input('action');
                                $query->where('action', 'like', $action . '%');
                            }

                            if ($request->filled('resource_type')) {
                                $query->where('model_type', $request->input('resource_type'));
                            }

                            if ($request->filled('resource_id')) {
                                $query->where('model_id', (int) $request->input('resource_id'));
                            }

                            if ($request->filled('admin_id')) {
                                $query->where('usuario_id', (int) $request->input('admin_id'));
                            }

                            if ($request->filled('data_inicio')) {
                                $query->whereDate('criado_em', '>=', $request->input('data_inicio'));
                            }

                            if ($request->filled('data_fim')) {
                                $query->whereDate('criado_em', '<=', $request->input('data_fim'));
                            }

                            return $query->get()->map(function (AuditLog $log) use ($tenantIdLoop) {
                                return [
                                    'id'            => $log->id,
                                    'admin_id'      => $log->usuario_id,
                                    'action'        => $log->action,
                                    'resource_type' => $log->model_type,
                                    'resource_id'   => $log->model_id,
                                    'tenant_id'     => $tenantIdLoop,
                                    'empresa_id'    => null,
                                    'ip_address'    => $log->ip_address,
                                    'user_agent'    => $log->user_agent,
                                    'context'       => [
                                        'old_values' => $log->old_values,
                                        'new_values' => $log->new_values,
                                        'changes'    => $log->changes,
                                    ],
                                    'created_at'    => $log->criado_em,
                                    'updated_at'    => $log->atualizado_em,
                                ];
                            })->toArray();
                        });

                        if (!empty($tenantItems)) {
                            $items = array_merge($items, $tenantItems);
                        }
                    } catch (\Throwable $e) {
                        $this->logAdminWarning('AdminAuditLogController::index - Falha ao coletar audit_logs de tenant', [
                            'tenant_id' => $tenantDomain->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                // Incluir também os logs administrativos centrais
                $centralQuery = AdminAuditLog::query()->orderByDesc('created_at');

                if ($request->filled('admin_id')) {
                    $centralQuery->where('admin_id', (int) $request->input('admin_id'));
                }

                if ($request->filled('action')) {
                    $action = $request->input('action');
                    $centralQuery->where('action', 'like', $action . '%');
                }

                if ($request->filled('resource_type')) {
                    $centralQuery->where('resource_type', $request->input('resource_type'));
                }

                if ($request->filled('resource_id')) {
                    $centralQuery->where('resource_id', $request->input('resource_id'));
                }

                if ($request->filled('data_inicio')) {
                    $centralQuery->whereDate('created_at', '>=', $request->input('data_inicio'));
                }

                if ($request->filled('data_fim')) {
                    $centralQuery->whereDate('created_at', '<=', $request->input('data_fim'));
                }

                $centralItems = $centralQuery->get()->map(function (AdminAuditLog $log) {
                    return [
                        'id'            => $log->id,
                        'admin_id'      => $log->admin_id,
                        'action'        => $log->action,
                        'resource_type' => $log->resource_type,
                        'resource_id'   => $log->resource_id,
                        'tenant_id'     => $log->tenant_id,
                        'empresa_id'    => $log->empresa_id,
                        'ip_address'    => $log->ip_address,
                        'user_agent'    => $log->user_agent,
                        'context'       => $log->context,
                        'created_at'    => $log->created_at,
                        'updated_at'    => $log->updated_at,
                    ];
                })->toArray();

                if (!empty($centralItems)) {
                    $items = array_merge($items, $centralItems);
                }

                // Ordenar por created_at/created_em (desc)
                usort($items, function (array $a, array $b) {
                    $aDate = $a['created_at'] ?? null;
                    $bDate = $b['created_at'] ?? null;

                    if ($aDate === $bDate) {
                        return 0;
                    }

                    // Queremos DESC (mais recentes primeiro)
                    return $aDate < $bDate ? 1 : -1;
                });

                $total = count($items);
                $lastPage = $perPage > 0 ? (int) ceil(max($total, 1) / $perPage) : 1;

                // Garantir que a página atual nunca ultrapasse o total de páginas
                if ($page > $lastPage) {
                    $page = $lastPage;
                }
                if ($page < 1) {
                    $page = 1;
                }

                $offset = ($page - 1) * $perPage;
                $pageItems = $perPage > 0 ? array_slice($items, $offset, $perPage) : $items;

                $this->logAdminInfo('AdminAuditLogController::index - Listando logs agregados de todos os tenants', [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'tenants_total' => $tenants->count(),
                ]);

                return ApiResponse::collection($pageItems, [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                ]);
            }

            /**
             * 🔹 Modo 3: Logs administrativos centrais (tabela admin_audit_logs no banco central)
             * Usado quando tenant_id NÃO é informado e all_tenants NÃO está ativo.
             */
            $query = AdminAuditLog::query()->orderByDesc('created_at');

            if ($request->filled('admin_id')) {
                $query->where('admin_id', (int) $request->input('admin_id'));
            }

            if ($request->filled('action')) {
                $action = $request->input('action');
                $query->where('action', 'like', $action . '%');
            }

            if ($request->filled('resource_type')) {
                $query->where('resource_type', $request->input('resource_type'));
            }

            if ($request->filled('resource_id')) {
                $query->where('resource_id', $request->input('resource_id'));
            }

            if ($request->filled('data_inicio')) {
                $query->whereDate('created_at', '>=', $request->input('data_inicio'));
            }

            if ($request->filled('data_fim')) {
                $query->whereDate('created_at', '<=', $request->input('data_fim'));
            }

            $paginado = $query->paginate($perPage);

            $this->logAdminInfo('AdminAuditLogController::index - Listando logs de auditoria', [
                'total' => $paginado->total(),
                'page' => $paginado->currentPage(),
                'per_page' => $paginado->perPage(),
            ]);

            return ApiResponse::collection($paginado->items(), [
                'total' => $paginado->total(),
                'per_page' => $paginado->perPage(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
            ]);
        } catch (\Throwable $e) {
            return $this->handleAdminException($e, 'Erro ao listar logs de auditoria.', 500);
        }
    }
}

