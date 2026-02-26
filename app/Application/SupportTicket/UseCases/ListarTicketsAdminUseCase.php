<?php

declare(strict_types=1);

namespace App\Application\SupportTicket\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\SupportTicket;
use App\Services\AdminTenancyRunner;
use Illuminate\Database\QueryException;

/**
 * Use Case: Listar tickets de suporte no admin.
 *
 * Lista tickets de todas as empresas ou de uma empresa (tenant) específica.
 * Respeita single-DB vs multi-DB (tenancy).
 */
class ListarTicketsAdminUseCase
{
    private const STATUS_VALIDOS = ['aberto', 'em_atendimento', 'resolvido'];

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * @param array{tenant_id?: int|null, per_page?: int, page?: int, status?: string} $filtros
     * @return array{data: array<int, array>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function executar(array $filtros): array
    {
        $tenantIdParam = $filtros['tenant_id'] ?? null;
        $hasValidTenantId = $tenantIdParam !== null
            && $tenantIdParam !== ''
            && (string) $tenantIdParam !== 'all'
            && (int) $tenantIdParam > 0;
        $listarTodas = ! $hasValidTenantId;
        $tenantId = $hasValidTenantId ? (int) $tenantIdParam : 0;

        $perPage = max(1, min(50, (int) ($filtros['per_page'] ?? 20)));
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $status = isset($filtros['status']) && \in_array($filtros['status'], self::STATUS_VALIDOS, true)
            ? $filtros['status']
            : null;
        $search = isset($filtros['search']) && \is_string($filtros['search'])
            ? trim($filtros['search'])
            : null;
        if ($search !== null && $search === '') {
            $search = null;
        }

        $useTenantDatabases = config('tenancy.database.use_tenant_databases', false);

        if ($listarTodas) {
            return $this->listarTodasEmpresas($perPage, $page, $status, $search, $useTenantDatabases);
        }

        return $this->listarPorTenant($tenantId, $perPage, $page, $status, $search, $useTenantDatabases);
    }

    /**
     * @return array{data: array, meta: array}
     */
    private function listarTodasEmpresas(int $perPage, int $page, ?string $status, ?string $search, bool $useTenantDatabases): array
    {
        $mapTicketToItem = $this->mapTicketToItem();

        if (! $useTenantDatabases) {
            $query = SupportTicket::query()
                ->with(['user', 'tenant'])
                ->withCount('responses')
                ->orderByDesc('created_at');
            if ($status !== null) {
                $query->where('status', $status);
            }
            $this->applySearch($query, $search);
            $tickets = $query->paginate($perPage, ['*'], 'page', $page);
            return [
                'data' => collect($tickets->items())->map($mapTicketToItem)->all(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ];
        }

        $allTenants = $this->tenantRepository->buscarComFiltros(['per_page' => 500])->getCollection();
        $merged = collect();
        foreach ($allTenants as $tenantDomain) {
            try {
                $items = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($status, $search) {
                    $query = SupportTicket::query()
                        ->with('user')
                        ->withCount('responses')
                        ->orderByDesc('created_at');
                    if ($status !== null) {
                        $query->where('status', $status);
                    }
                    $this->applySearch($query, $search);
                    return $query->get();
                });
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'relation "')) {
                    continue;
                }
                throw $e;
            }
            foreach ($items as $t) {
                $t->setRelation('tenant', (object) [
                    'id' => $tenantDomain->id,
                    'razao_social' => $tenantDomain->razaoSocial,
                    'nome_fantasia' => $tenantDomain->nomeFantasia ?? null,
                ]);
                $merged->push($t);
            }
        }
        $merged = $merged->sortByDesc(fn ($t) => $t->created_at)->values();
        $total = $merged->count();
        $slice = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $slice->map($mapTicketToItem)->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return array{data: array, meta: array}
     */
    private function listarPorTenant(
        int $tenantId,
        int $perPage,
        int $page,
        ?string $status,
        ?string $search,
        bool $useTenantDatabases
    ): array {
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (! $tenantDomain) {
            throw new \App\Domain\Exceptions\NotFoundException('Tenant');
        }

        $mapTicketToItem = $this->mapTicketToItem();
        $runQuery = function () use ($perPage, $status, $search, $mapTicketToItem, $tenantId, $tenantDomain) {
            $query = SupportTicket::query()
                ->with('user')
                ->withCount('responses')
                ->orderByDesc('created_at');
            if ($status !== null) {
                $query->where('status', $status);
            }
            $this->applySearch($query, $search);
            $tickets = $query->paginate($perPage);
            $items = collect($tickets->items())->map(function ($t) use ($mapTicketToItem, $tenantId, $tenantDomain) {
                $t->setAttribute('tenant_id', $tenantId);
                $t->setRelation('tenant', (object) [
                    'id' => $tenantDomain->id,
                    'razao_social' => $tenantDomain->razaoSocial,
                    'nome_fantasia' => $tenantDomain->nomeFantasia ?? null,
                ]);
                return $mapTicketToItem($t);
            })->all();
            return [
                'data' => $items,
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ];
        };

        if (! $useTenantDatabases) {
            $query = SupportTicket::query()
                ->where('tenant_id', $tenantId)
                ->with(['user', 'tenant'])
                ->withCount('responses')
                ->orderByDesc('created_at');
            if ($status !== null) {
                $query->where('status', $status);
            }
            $this->applySearch($query, $search);
            $tickets = $query->paginate($perPage, ['*'], 'page', $page);
            return [
                'data' => collect($tickets->items())->map($mapTicketToItem)->all(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ];
        }

        return $this->adminTenancyRunner->runForTenant($tenantDomain, $runQuery);
    }

    private function applySearch(\Illuminate\Database\Eloquent\Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }
        $term = '%' . addcslashes($search, '%_\\') . '%';
        $query->where(function ($q) use ($term) {
            $q->where('numero', 'like', $term)
                ->orWhere('descricao', 'like', $term);
        });
    }

    private function mapTicketToItem(): \Closure
    {
        return function ($t) {
            $arr = $t->toArray();
            $tenant = $t->tenant ?? null;
            $arr['empresa_nome'] = $tenant
                ? ($tenant->razao_social ?? $tenant->nome_fantasia ?? 'Empresa #' . $t->tenant_id)
                : ('Empresa #' . ($t->tenant_id ?? ''));
            return $arr;
        };
    }
}
