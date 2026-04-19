<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Suporte\Entities\Ticket;
use App\Domain\Suporte\Entities\TicketResponse;
use App\Domain\Suporte\Repositories\TicketRepositoryInterface;
use App\Modules\Suporte\Models\Ticket as TicketModel;
use App\Modules\Suporte\Models\TicketResponse as TicketResponseModel;
use Illuminate\Support\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    public function criar(Ticket $ticket): Ticket
    {
        $model = TicketModel::create([
            'numero' => null,
            'user_id' => $ticket->userId,
            'empresa_id' => $ticket->empresaId,
            'descricao' => $ticket->descricao,
            'anexo_url' => $ticket->anexoUrl,
            'status' => $ticket->status,
            'observacao_interna' => $ticket->observacaoInterna,
        ]);

        $model->numero = TicketModel::numeroFromId($model->id);
        $model->save();

        $model->loadCount('responses');

        return $this->toDomain($model->fresh(['responses']));
    }

    public function listarPorUsuario(int $userId, ?int $empresaId, int $limit = 50): Collection
    {
        $query = TicketModel::query()
            ->where('user_id', $userId)
            ->withCount('responses')
            ->orderByDesc('updated_at')
            ->limit($limit);

        if (!empty($empresaId)) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get()->map(fn (TicketModel $model) => $this->toDomain($model));
    }

    public function buscarPorIdEUsuario(int $id, int $userId, ?int $empresaId): ?Ticket
    {
        $query = TicketModel::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->withCount('responses')
            ->with([
                'responses' => function ($q) {
                    $q->orderBy('created_at', 'asc');
                },
            ]);

        if (!empty($empresaId)) {
            $query->where('empresa_id', $empresaId);
        }

        $model = $query->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(TicketModel $model): Ticket
    {
        return new Ticket(
            id: $model->id,
            numero: $model->numero,
            userId: $model->user_id,
            empresaId: $model->empresa_id,
            descricao: $model->descricao,
            anexoUrl: $model->anexo_url,
            status: $model->status,
            observacaoInterna: $model->observacao_interna,
            responsesCount: (int) ($model->responses_count ?? 0),
            responses: $this->mapResponses($model),
            createdAt: $model->created_at?->toISOString(),
            updatedAt: $model->updated_at?->toISOString(),
        );
    }

    /**
     * @return TicketResponse[]
     */
    private function mapResponses(TicketModel $model): array
    {
        if (!$model->relationLoaded('responses')) {
            return [];
        }

        return $model->responses
            ->map(function (TicketResponseModel $response) {
                return new TicketResponse(
                    id: $response->id,
                    ticketId: $response->ticket_id,
                    userId: $response->user_id,
                    authorType: $response->author_type,
                    mensagem: $response->mensagem,
                    createdAt: $response->created_at?->toISOString(),
                    updatedAt: $response->updated_at?->toISOString(),
                );
            })
            ->values()
            ->all();
    }
}
