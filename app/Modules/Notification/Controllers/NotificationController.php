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
}



