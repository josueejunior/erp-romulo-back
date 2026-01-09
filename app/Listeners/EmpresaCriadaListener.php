<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Tenant\Events\EmpresaCriada;
use App\Mail\EmpresaCriadaEmail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listener para evento EmpresaCriada
 * Envia email de boas-vindas quando uma empresa é criada
 */
class EmpresaCriadaListener
{
    /**
     * Handle the event.
     */
    public function handle(EmpresaCriada $event): void
    {
        Log::info('EmpresaCriadaListener::handle iniciado', [
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
            'email' => $event->email,
        ]);

        try {
            // Buscar dados completos do tenant
            $tenant = Tenant::find($event->tenantId);
            
            if (!$tenant) {
                Log::warning('EmpresaCriadaListener - Tenant não encontrado', [
                    'tenant_id' => $event->tenantId,
                ]);
                return;
            }

            // Preparar dados para o email
            $tenantData = [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social ?? $event->razaoSocial,
                'cnpj' => $tenant->cnpj ?? $event->cnpj,
                'email' => $tenant->email ?? $event->email,
                'status' => $tenant->status ?? 'ativa',
            ];

            $empresaData = [
                'id' => $event->empresaId,
                'razao_social' => $event->razaoSocial,
            ];

            // Determinar email de destino
            $emailDestino = $event->email ?? $tenant->email;
            
            if (!$emailDestino) {
                Log::warning('EmpresaCriadaListener - Email não disponível para envio', [
                    'tenant_id' => $event->tenantId,
                    'tenant_email' => $tenant->email,
                    'event_email' => $event->email,
                ]);
                return;
            }

            Log::info('EmpresaCriadaListener - Enviando email', [
                'tenant_id' => $event->tenantId,
                'email_destino' => $emailDestino,
                'mail_driver' => config('mail.default'),
            ]);

            // Enviar email
            Mail::to($emailDestino)->send(new EmpresaCriadaEmail($tenantData, $empresaData));
            
            Log::info('EmpresaCriadaListener - Email enviado com sucesso', [
                'tenant_id' => $event->tenantId,
                'email_destino' => $emailDestino,
            ]);

        } catch (\Exception $e) {
            // Não quebrar o fluxo de criação se houver erro no email
            Log::error('EmpresaCriadaListener - Erro ao enviar email', [
                'tenant_id' => $event->tenantId,
                'email' => $event->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

