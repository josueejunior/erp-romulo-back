<?php

namespace App\Application\Suporte\UseCases;

use App\Domain\Suporte\Repositories\TicketRepositoryInterface;
use Illuminate\Support\Collection;

class ListarTicketsUseCase
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    ) {}

    /**
     * @return Collection<int, \App\Domain\Suporte\Entities\Ticket>
     */
    public function executar(int $userId, ?int $empresaId, int $limit = 50): Collection
    {
        return $this->ticketRepository->listarPorUsuario($userId, $empresaId, $limit);
    }
}
