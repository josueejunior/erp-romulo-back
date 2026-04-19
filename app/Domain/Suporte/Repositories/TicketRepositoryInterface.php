<?php

namespace App\Domain\Suporte\Repositories;

use App\Domain\Suporte\Entities\Ticket;
use Illuminate\Support\Collection;

interface TicketRepositoryInterface
{
    public function criar(Ticket $ticket): Ticket;

    /**
     * @return Collection<int, Ticket>
     */
    public function listarPorUsuario(int $userId, ?int $empresaId, int $limit = 50): Collection;

    public function buscarPorIdEUsuario(int $id, int $userId, ?int $empresaId): ?Ticket;
}
