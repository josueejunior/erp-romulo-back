<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Assinatura\Events\AssinaturaCriada;
use App\Domain\Assinatura\Events\AssinaturaAtualizada;
use App\Mail\AssinaturaNotificacaoEmail;
use App\Models\Tenant;
use App\Models\TenantEmpresa;
use App\Models\Empresa;
use App\Modules\Assinatura\Models\Assinatura as AssinaturaModel;
use App\Modules\Assinatura\Models\Plano;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Listener para eventos de Assinatura
 * Envia email de notificação quando assinatura é criada ou atualizada
 */
class AssinaturaNotificacaoListener
{
    /**
     * Handle AssinaturaCriada event.
     */
    public function handleAssinaturaCriada(AssinaturaCriada $event): void
    {
        $this->enviarNotificacao($event, isNovaAssinatura: true);
    }

    /**
     * Handle AssinaturaAtualizada event.
     */
    public function handleAssinaturaAtualizada(AssinaturaAtualizada $event): void
    {
        $this->enviarNotificacao($event, isNovaAssinatura: false);
    }

    /**
     * Envia notificação por email
     */
    private function enviarNotificacao(AssinaturaCriada|AssinaturaAtualizada $event, bool $isNovaAssinatura): void
    {
        Log::info('AssinaturaNotificacaoListener - Iniciando envio de notificação', [
            'assinatura_id' => $event->assinaturaId,
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
            'is_nova' => $isNovaAssinatura,
        ]);

        try {
            // Buscar tenant
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                Log::warning('AssinaturaNotificacaoListener - Tenant não encontrado', [
                    'tenant_id' => $event->tenantId,
                ]);
                return;
            }

            // Inicializar contexto do tenant
            Tenancy::initialize($tenant);

            try {
                // Buscar assinatura completa
                $assinaturaModel = AssinaturaModel::find($event->assinaturaId);
                if (!$assinaturaModel) {
                    Log::warning('AssinaturaNotificacaoListener - Assinatura não encontrada', [
                        'assinatura_id' => $event->assinaturaId,
                    ]);
                    return;
                }

                // Buscar plano
                $plano = Plano::find($event->planoId);
                if (!$plano) {
                    Log::warning('AssinaturaNotificacaoListener - Plano não encontrado', [
                        'plano_id' => $event->planoId,
                    ]);
                    return;
                }

                // Buscar empresa
                $empresa = Empresa::find($event->empresaId);
                if (!$empresa) {
                    Log::warning('AssinaturaNotificacaoListener - Empresa não encontrada', [
                        'empresa_id' => $event->empresaId,
                    ]);
                    return;
                }

                // Buscar usuário para obter email (se não foi fornecido no evento)
                $emailDestino = $event->emailDestino;
                if (!$emailDestino && $event->userId) {
                    $user = User::find($event->userId);
                    if ($user) {
                        $emailDestino = $user->email;
                    }
                }

                // Fallback: usar email do tenant
                if (!$emailDestino) {
                    $emailDestino = $tenant->email;
                }

                if (!$emailDestino) {
                    Log::warning('AssinaturaNotificacaoListener - Email de destino não encontrado', [
                        'tenant_id' => $event->tenantId,
                        'user_id' => $event->userId,
                    ]);
                    return;
                }

                // Preparar dados para o email
                $assinaturaData = [
                    'id' => $assinaturaModel->id,
                    'status' => $assinaturaModel->status,
                    'valor_pago' => $assinaturaModel->valor_pago,
                    'metodo_pagamento' => $assinaturaModel->metodo_pagamento,
                    'data_inicio' => $assinaturaModel->data_inicio?->toDateString(),
                    'data_fim' => $assinaturaModel->data_fim?->toDateString(),
                    'dias_grace_period' => $assinaturaModel->dias_grace_period,
                ];

                $planoData = [
                    'id' => $plano->id,
                    'nome' => $plano->nome,
                    'preco_mensal' => $plano->preco_mensal,
                ];

                $empresaData = [
                    'id' => $empresa->id,
                    'razao_social' => $empresa->razao_social,
                ];

                // Enviar email
                Mail::to($emailDestino)->send(
                    new AssinaturaNotificacaoEmail($assinaturaData, $planoData, $empresaData, $isNovaAssinatura)
                );

                Log::info('AssinaturaNotificacaoListener - Email enviado com sucesso', [
                    'assinatura_id' => $event->assinaturaId,
                    'email_destino' => $emailDestino,
                    'is_nova' => $isNovaAssinatura,
                ]);

            } finally {
                if (Tenancy::initialized()) {
                    Tenancy::end();
                }
            }

        } catch (\Exception $e) {
            Log::error('AssinaturaNotificacaoListener - Erro ao enviar email', [
                'assinatura_id' => $event->assinaturaId,
                'tenant_id' => $event->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}


