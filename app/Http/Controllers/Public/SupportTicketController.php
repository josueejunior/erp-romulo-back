<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UploadController as UploadControllerBase;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller para abertura e acompanhamento de tickets de suporte pelo usuário.
 */
class SupportTicketController extends Controller
{
    /**
     * Lista os tickets do usuário autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->withCount('responses')
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Exibe um ticket do usuário com as respostas (se for dono do ticket).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        $ticket = SupportTicket::query()
            ->where('user_id', $user->id)
            ->with(['responses'])
            ->find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket não encontrado.'], 404);
        }

        $data = $ticket->toArray();
        $data['anexo_view_url'] = $ticket->anexo_url ? UploadControllerBase::signedUrlForAnexo($ticket->anexo_url) : null;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Cria um novo ticket de suporte.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'descricao' => 'required|string|max:5000',
            'anexo_url' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        $tenantId = null;
        $empresaId = null;
        if (function_exists('tenant') && tenant()) {
            $tenantId = tenant('id');
        }
        if ($request->user()->empresa_ativa_id ?? null) {
            $empresaId = $request->user()->empresa_ativa_id;
        }

        $numero = Cache::lock('support_ticket_numero', 5)->block(3, function () {
            return SupportTicket::gerarNumero();
        });

        try {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'numero' => $numero,
                'descricao' => $validated['descricao'],
                'anexo_url' => $validated['anexo_url'] ?? null,
                'status' => 'aberto',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket criado com sucesso.',
                'ticket' => [
                    'id' => $ticket->id,
                    'numero' => $ticket->numero,
                    'descricao' => $ticket->descricao,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at?->format('c'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('SupportTicketController::store', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar ticket. Tente novamente.',
            ], 500);
        }
    }
}
