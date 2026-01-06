<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * GET /notifications
     * Obter todas as notificações do usuário/empresa
     */
    public function index(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresaAtiva;
        
        if (!$empresa) {
            return response()->json([
                'message' => 'Nenhuma empresa ativa encontrada'
            ], 400);
        }

        $tenantId = tenancy()->tenant?->id;
        
        $notificacoes = $this->notificationService->obterNotificacoes(
            $empresa->id,
            $tenantId
        );

        return response()->json($notificacoes);
    }
}


