<?php

declare(strict_types=1);

namespace App\Application\SupportTicket\UseCases;

use App\Domain\Exceptions\NotFoundException;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\SupportTicket;
use App\Services\AdminTenancyRunner;
use Illuminate\Database\QueryException;

/**
 * Use Case: Buscar um ticket de suporte no admin.
 *
 * Com tenant_id: busca no banco daquele tenant.
 * Sem tenant_id: busca em todos os tenants (link direto /admin/tickets/1).
 */
class BuscarTicketAdminUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * @param int $ticketId ID do ticket
     * @param int $tenantId 0 = buscar em todos os tenants
     * @return array Dados do ticket (para JSON)
     */
    public function executar(int $ticketId, int $tenantId = 0): array
    {
        $useTenantDatabases = config('tenancy.database.use_tenant_databases', false);
        $toData = fn ($ticket) => $this->ticketToArray($ticket);

        if ($tenantId > 0) {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (! $tenantDomain) {
                throw new NotFoundException('Tenant');
            }
            if (! $useTenantDatabases) {
                $ticket = SupportTicket::query()
                    ->where('tenant_id', $tenantId)
                    ->with(['user', 'responses'])
                    ->find($ticketId);
                if ($ticket === null) {
                    throw new NotFoundException('Ticket');
                }
                return $toData($ticket);
            }
            try {
                $data = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($ticketId, $toData) {
                    $ticket = SupportTicket::query()->with(['user', 'responses'])->find($ticketId);
                    return $ticket ? $toData($ticket) : null;
                });
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'relation "')) {
                    throw new NotFoundException('Ticket');
                }
                throw $e;
            }
            if ($data === null) {
                throw new NotFoundException('Ticket');
            }
            return $data;
        }

        // Sem tenant_id: buscar em todos (link direto)
        if (! $useTenantDatabases) {
            $ticket = SupportTicket::query()
                ->with(['user', 'responses', 'tenant'])
                ->find($ticketId);
            if ($ticket === null) {
                throw new NotFoundException('Ticket');
            }
            return $toData($ticket);
        }

        $allTenants = $this->tenantRepository->buscarComFiltros(['per_page' => 500])->getCollection();
        foreach ($allTenants as $tenantDomain) {
            try {
                $found = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($ticketId, $toData) {
                    $ticket = SupportTicket::query()->with(['user', 'responses'])->find($ticketId);
                    return $ticket ? $toData($ticket) : null;
                });
                if ($found !== null) {
                    if (! isset($found['tenant_id'])) {
                        $found['tenant_id'] = $tenantDomain->id;
                    }
                    return $found;
                }
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'does not exist') && ! str_contains($e->getMessage(), 'relation "')) {
                    throw $e;
                }
            }
        }

        throw new NotFoundException('Ticket');
    }

    private function ticketToArray(SupportTicket $ticket): array
    {
        return $ticket->toArray();
    }
}
