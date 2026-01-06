<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\BemVindoEmail;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;

class EnviarEmailBemVindo
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        try {
            $user = $event->user;
            
            // Verificar se é admin (AdminUser não tem tenant)
            if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
                return; // Não enviar email para admins
            }
            
            // Buscar tenant do usuário
            $tenant = null;
            $assinatura = null;
            
            // Tentar buscar tenant de várias formas
            // 1. Se o usuário tem empresa_ativa_id, buscar pela empresa
            if (isset($user->empresa_ativa_id) && $user->empresa_ativa_id) {
                $empresa = \App\Models\Empresa::find($user->empresa_ativa_id);
                if ($empresa && isset($empresa->tenant_id)) {
                    $tenant = Tenant::find($empresa->tenant_id);
                }
            }
            
            // 2. Se não encontrou, buscar por todos os tenants procurando o usuário
            if (!$tenant) {
                $tenants = Tenant::all();
                foreach ($tenants as $t) {
                    try {
                        tenancy()->initialize($t);
                        try {
                            $userInTenant = \App\Modules\Auth\Models\User::where('email', $user->email)->first();
                            if ($userInTenant) {
                                $tenant = $t;
                                break;
                            }
                        } finally {
                            if (tenancy()->initialized) {
                                tenancy()->end();
                            }
                        }
                    } catch (\Exception $e) {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                        continue;
                    }
                }
            }
            
            // Se encontrou tenant, buscar assinatura
            if ($tenant) {
                // Inicializar contexto do tenant para buscar assinatura
                tenancy()->initialize($tenant);
                
                try {
                    $assinatura = Assinatura::where('tenant_id', $tenant->id)
                        ->where('status', 'ativa')
                        ->with('plano')
                        ->first();
                } finally {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }
            
            // Preparar dados do usuário
            $userData = [
                'name' => $user->name ?? $user->nome ?? 'Usuário',
                'email' => $user->email,
            ];
            
            // Preparar dados do tenant
            $tenantData = $tenant ? [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
            ] : null;
            
            // Enviar email apenas se tiver email válido
            if ($user->email) {
                Mail::to($user->email)->send(new BemVindoEmail($userData, $tenantData, $assinatura));
                
                Log::info('Email de boas-vindas enviado', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'tenant_id' => $tenant?->id,
                    'assinatura_id' => $assinatura?->id,
                ]);
            }
            
        } catch (\Exception $e) {
            // Não quebrar o fluxo de login se houver erro no email
            Log::error('Erro ao enviar email de boas-vindas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

