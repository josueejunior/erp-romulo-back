<?php

declare(strict_types=1);

namespace App\Application\SupportTicket\UseCases;

use App\Domain\Exceptions\NotFoundException;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\SupportTicket;
use App\Services\AdminTenancyRunner;

/**
 * Use Case: Atualizar status de um ticket de suporte no admin.
 */
class AtualizarStatusTicketAdminUseCase
{
    private const STATUS_VALIDOS = ['aberto', 'em_atendimento', 'resolvido'];

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * @param int $ticketId ID do ticket
     * @param int $tenantId ID da empresa (obrigatório)
     * @param string $status aberto | em_atendimento | resolvido
     * @return array Dados do ticket atualizado (para JSON)
     */
    public function executar(int $ticketId, int $tenantId, string $status): array
    {
        if (! \in_array($status, self::STATUS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (! $tenantDomain) {
            throw new NotFoundException('Tenant');
        }

        $useTenantDatabases = config('tenancy.database.use_tenant_databases', false);

        if (! $useTenantDatabases) {
            $ticket = SupportTicket::query()->where('tenant_id', $tenantId)->find($ticketId);
            if ($ticket === null) {
                throw new NotFoundException('Ticket');
            }
            $ticket->update(['status' => $status]);
            return $ticket->fresh()->toArray();
        }

        $ticket = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($ticketId, $status) {
            $ticket = SupportTicket::query()->find($ticketId);
            if ($ticket === null) {
                return null;
            }
            $ticket->update(['status' => $status]);
            return $ticket->fresh();
        });

        if ($ticket === null) {
            throw new NotFoundException('Ticket');
        }

        return $ticket->toArray();
    }
}
