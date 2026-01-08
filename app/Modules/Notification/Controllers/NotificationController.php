<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use HasAuthContext;

    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * GET /notifications
     * Obter todas as notificações do usuário/empresa
     */
    public function index(Request $request): JsonResponse
    {
        $empresa = $this->getEmpresa();
        
        if (!$empresa) {
            return response()->json([
                'message' => 'Nenhuma empresa ativa encontrada'
            ], 400);
        }

        $tenantId = $this->getTenantId();
        
        $notificacoes = $this->notificationService->obterNotificacoes(
            $empresa->id,
            $tenantId
        );

        return response()->json($notificacoes);
    }

    /**
     * PATCH /notifications/{id}/read
     * Marcar uma notificação como lida
     * 
     * Nota: As notificações são geradas dinamicamente e não persistidas.
     * Este endpoint é um placeholder para compatibilidade com o frontend.
     * Em uma implementação real, você armazenaria o estado de leitura no banco.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        // Como as notificações são geradas dinamicamente, 
        // apenas retornamos sucesso para o frontend poder gerenciar o estado local
        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como lida',
            'id' => $id,
        ]);
    }

    /**
     * POST /notifications/read-all
     * Marcar todas as notificações como lidas
     * 
     * Nota: Placeholder - veja comentário em markAsRead
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Todas as notificações foram marcadas como lidas',
        ]);
    }
}



