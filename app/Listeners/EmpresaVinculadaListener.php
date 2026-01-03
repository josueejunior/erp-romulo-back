<?php

namespace App\Listeners;

use App\Domain\Tenant\Events\EmpresaVinculada;
use Illuminate\Support\Facades\Log;

/**
 * Listener para evento EmpresaVinculada
 * Ações quando empresa é vinculada a usuário
 */
class EmpresaVinculadaListener
{
    /**
     * Handle the event.
     */
    public function handle(EmpresaVinculada $event): void
    {
        // Log de auditoria
        Log::info('Empresa vinculada a usuário', [
            'user_id' => $event->userId,
            'empresa_id' => $event->empresaId,
            'tenant_id' => $event->tenantId,
            'perfil' => $event->perfil,
        ]);

        // Pode atualizar cache, notificar usuário, etc.
    }
}



