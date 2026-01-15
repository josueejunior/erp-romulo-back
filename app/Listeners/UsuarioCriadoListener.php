<?php

namespace App\Listeners;

use App\Domain\Auth\Events\UsuarioCriado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\BemVindoEmail;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;

/**
 * Listener para evento UsuarioCriado
 * Ações secundárias desacopladas do domínio
 */
class UsuarioCriadoListener
{
    /**
     * Handle the event.
     */
    public function handle(UsuarioCriado $event): void
    {
        Log::info('UsuarioCriadoListener::handle iniciado', [
            'user_id' => $event->userId,
            'email' => $event->email,
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
        ]);

        try {
            // Log de auditoria
            Log::info('Usuário criado - Evento recebido', [
                'user_id' => $event->userId,
                'email' => $event->email,
                'tenant_id' => $event->tenantId,
                'empresa_id' => $event->empresaId,
            ]);

            // Buscar tenant
            $tenant = null;
            $assinatura = null;
            
            if ($event->tenantId) {
                $tenant = Tenant::find($event->tenantId);
                
                // Se encontrou tenant, buscar assinatura ativa
                if ($tenant) {
                    // Inicializar contexto do tenant para buscar assinatura
                    tenancy()->initialize($tenant);
                    
                    try {
                        // 🔥 CORREÇÃO: Usar criado_em em vez de created_at (timestamps customizados)
                        $assinatura = Assinatura::where('tenant_id', $tenant->id)
                            ->where('status', 'ativa')
                            ->with('plano')
                            ->orderBy('criado_em', 'desc')
                            ->first();
                    } finally {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                    }
                }
            }
            
            // Preparar dados do usuário
            $userData = [
                'name' => $event->nome ?? 'Usuário',
                'email' => $event->email,
            ];
            
            // Preparar dados do tenant
            $tenantData = $tenant ? [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
            ] : null;
            
            // Enviar e-mail de boas-vindas
            if ($event->email) {
                Log::info('UsuarioCriadoListener - Tentando enviar email', [
                    'email' => $event->email,
                    'mail_driver' => config('mail.default'),
                ]);

                Mail::to($event->email)->send(new BemVindoEmail($userData, $tenantData, $assinatura));
                
                Log::info('Email de boas-vindas enviado após cadastro', [
                    'user_id' => $event->userId,
                    'email' => $event->email,
                    'tenant_id' => $tenant?->id,
                    'assinatura_id' => $assinatura?->id,
                    'mail_driver' => config('mail.default'),
                ]);
            } else {
                Log::warning('UsuarioCriadoListener - Email não fornecido no evento', [
                    'user_id' => $event->userId,
                ]);
            }
            
        } catch (\Exception $e) {
            // Não quebrar o fluxo de criação se houver erro no email
            Log::error('Erro ao enviar email de boas-vindas após cadastro', [
                'user_id' => $event->userId,
                'email' => $event->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}




