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
     * @param array $dados ['status' => string?, 'observacao_interna' => string?]
     * @return array Dados do ticket atualizado (para JSON)
     */
    public function executar(int $ticketId, int $tenantId, array $dados): array
    {
        $status = $dados['status'] ?? null;
        if ($status !== null && ! \in_array($status, self::STATUS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (! $tenantDomain) {
            throw new NotFoundException('Tenant');
        }

        $updates = [];
        if ($status !== null) {
            $updates['status'] = $status;
        }
        if (array_key_exists('observacao_interna', $dados)) {
            $updates['observacao_interna'] = $dados['observacao_interna'];
        }
        if (empty($updates)) {
            $ticket = SupportTicket::query()->where('tenant_id', $tenantId)->find($ticketId);
            if ($ticket === null) {
                throw new NotFoundException('Ticket');
            }
            return $ticket->fresh()->toArray();
        }

        $useTenantDatabases = config('tenancy.database.use_tenant_databases', false);

        if (! $useTenantDatabases) {
            $ticket = SupportTicket::query()->where('tenant_id', $tenantId)->find($ticketId);
            if ($ticket === null) {
                throw new NotFoundException('Ticket');
            }
            $ticket->update($updates);
            return $ticket->fresh()->toArray();
        }

        $ticket = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($ticketId, $updates) {
            $ticket = SupportTicket::query()->find($ticketId);
            if ($ticket === null) {
                return null;
            }
            $ticket->update($updates);
            return $ticket->fresh();
        });

        if ($ticket === null) {
            throw new NotFoundException('Ticket');
        }

        return $ticket->toArray();
    }
}
