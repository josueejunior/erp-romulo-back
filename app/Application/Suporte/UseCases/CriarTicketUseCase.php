<?php

namespace App\Application\Suporte\UseCases;

use App\Application\Suporte\DTOs\CriarTicketDTO;
use App\Domain\Suporte\Entities\Ticket;
use App\Domain\Suporte\Repositories\TicketRepositoryInterface;

class CriarTicketUseCase
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    ) {}

    public function executar(CriarTicketDTO $dto): Ticket
    {
        $ticket = new Ticket(
            id: null,
            numero: null,
            userId: $dto->userId,
            empresaId: $dto->empresaId,
            descricao: trim($dto->descricao),
            anexoUrl: $dto->anexoUrl,
            status: 'aberto',
        );

        return $this->ticketRepository->criar($ticket);
    }
}
