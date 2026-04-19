<?php

namespace App\Modules\Suporte\Controllers;

use App\Application\Suporte\DTOs\CriarTicketDTO;
use App\Application\Suporte\Resources\TicketResource;
use App\Application\Suporte\UseCases\BuscarTicketUseCase;
use App\Application\Suporte\UseCases\CriarTicketUseCase;
use App\Application\Suporte\UseCases\ListarTicketsUseCase;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private ListarTicketsUseCase $listarTicketsUseCase,
        private BuscarTicketUseCase $buscarTicketUseCase,
        private CriarTicketUseCase $criarTicketUseCase,
        private TicketResource $ticketResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $userId = (int) $this->getUserId();
            $empresaId = $this->getEmpresaId();
            $perPage = max(1, min((int) $request->query('per_page', 50), 100));

            $tickets = $this->listarTicketsUseCase->executar($userId, $empresaId, $perPage);

            return response()->json([
                'data' => $tickets
                    ->map(fn ($ticket) => $this->ticketResource->toArray($ticket))
                    ->values()
                    ->all(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao carregar tickets');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $userId = (int) $this->getUserId();
            $empresaId = $this->getEmpresaId();

            $ticket = $this->buscarTicketUseCase->executar($id, $userId, $empresaId);

            if (!$ticket) {
                return response()->json([
                    'message' => 'Ticket não encontrado',
                ], 404);
            }

            return response()->json([
                'data' => $this->ticketResource->toArray($ticket),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao carregar ticket');
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'descricao' => ['required', 'string', 'max:5000'],
                'anexo_url' => ['nullable', 'string', 'max:2048'],
            ]);

            $userId = (int) $this->getUserId();
            $empresa = $this->getEmpresaAtivaOrFail();

            $dto = CriarTicketDTO::fromArray([
                'user_id' => $userId,
                'empresa_id' => $empresa->id,
                'descricao' => $validated['descricao'],
                'anexo_url' => $validated['anexo_url'] ?? null,
            ]);

            $ticket = $this->criarTicketUseCase->executar($dto);
            $ticketData = $this->ticketResource->toArray($ticket);

            return response()->json([
                'message' => 'Ticket criado com sucesso!',
                'numero' => $ticket->numero,
                'ticket' => $ticketData,
                'data' => [
                    'ticket' => $ticketData,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao enviar ticket');
        }
    }
}
