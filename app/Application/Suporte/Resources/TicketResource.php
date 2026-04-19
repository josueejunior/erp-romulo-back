<?php

namespace App\Application\Suporte\Resources;

use App\Domain\Suporte\Entities\Ticket;

class TicketResource
{
    public function toArray(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'numero' => $ticket->numero,
            'user_id' => $ticket->userId,
            'empresa_id' => $ticket->empresaId,
            'descricao' => $ticket->descricao,
            'anexo_url' => $ticket->anexoUrl,
            'anexo_view_url' => $ticket->anexoUrl,
            'status' => $ticket->status,
            'observacao_interna' => $ticket->observacaoInterna,
            'responses_count' => $ticket->responsesCount,
            'created_at' => $ticket->createdAt,
            'updated_at' => $ticket->updatedAt,
            'responses' => array_map(function ($response) {
                return [
                    'id' => $response->id,
                    'ticket_id' => $response->ticketId,
                    'user_id' => $response->userId,
                    'author_type' => $response->authorType,
                    'mensagem' => $response->mensagem,
                    'created_at' => $response->createdAt,
                    'updated_at' => $response->updatedAt,
                ];
            }, $ticket->responses),
        ];
    }
}
