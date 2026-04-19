<?php

namespace App\Application\Suporte\UseCases;

use App\Domain\Suporte\Entities\Ticket;
use App\Domain\Suporte\Repositories\TicketRepositoryInterface;

class BuscarTicketUseCase
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    ) {}

    public function executar(int $id, int $userId, ?int $empresaId): ?Ticket
    {
        return $this->ticketRepository->buscarPorIdEUsuario($id, $userId, $empresaId);
    }
}
