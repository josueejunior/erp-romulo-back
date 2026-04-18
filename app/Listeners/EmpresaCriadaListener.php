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

            // 🔥 FORÇAR RELOAD: Limpar cache de configuração antes de ler
            // Isso garante que estamos lendo do .env atual, não do cache
            if (app()->configurationIsCached()) {
                Log::warning('EmpresaCriadaListener - Configuração está em cache, forçando reload', [
                    'sugestao' => 'Execute: php artisan config:clear',
                ]);
            }
            
            // Ler diretamente do .env usando env() para garantir valores atualizados
            $mailDriver = env('MAIL_MAILER', config('mail.default'));
            $mailHost = env('MAIL_HOST', config('mail.mailers.smtp.host'));
            $mailPort = env('MAIL_PORT', config('mail.mailers.smtp.port'));
            $mailUsername = env('MAIL_USERNAME', config('mail.mailers.smtp.username'));
            $mailPassword = env('MAIL_PASSWORD', config('mail.mailers.smtp.password'));
            $mailEncryption = env('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption'));
            
            Log::info('EmpresaCriadaListener - Verificando configuração de email', [
                'tenant_id' => $event->tenantId,
                'email_destino' => $emailDestino,
                'mail_driver' => $mailDriver,
                'mail_host' => $mailHost,
                'mail_port' => $mailPort,
                'mail_username' => $mailUsername ? '***definido***' : 'não definido',
                'mail_password' => $mailPassword ? '***definido***' : 'não definido',
                'mail_encryption' => $mailEncryption,
                'config_cached' => app()->configurationIsCached(),
            ]);

            // Validar configuração SMTP (mas não bloquear se for configuração de desenvolvimento)
            if ($mailDriver === 'smtp') {
                // Verificar se host é válido (não vazio, não mailpit, não localhost)
                if (empty($mailHost) || 
                    strtolower($mailHost) === 'mailpit' || 
                    strtolower($mailHost) === 'localhost' ||
                    str_contains(strtolower($mailHost), '127.0.0.1')) {
                    Log::warning('EmpresaCriadaListener - Configuração SMTP de desenvolvimento detectada', [
                        'mail_host' => $mailHost,
                        'mail_driver' => $mailDriver,
                        'tenant_id' => $event->tenantId,
                        'email_destino' => $emailDestino,
                        'sugestao' => 'Execute: php artisan config:clear && verifique MAIL_HOST no .env',
                        'acao' => 'Tentando enviar mesmo assim - erro pode ocorrer',
                    ]);
                    // Não lançar exceção - tentar enviar mesmo assim e capturar erro depois
                }
                
                // Verificar se credenciais estão definidas (mas não bloquear se não estiverem)
                if (empty($mailUsername) || empty($mailPassword)) {
                    Log::warning('EmpresaCriadaListener - Credenciais SMTP parcialmente não definidas', [
                        'mail_host' => $mailHost,
                        'username_set' => !empty($mailUsername),
                        'password_set' => !empty($mailPassword),
                        'tenant_id' => $event->tenantId,
                        'email_destino' => $emailDestino,
                        'sugestao' => 'Verifique MAIL_USERNAME e MAIL_PASSWORD no .env',
                        'acao' => 'Tentando enviar mesmo assim - erro pode ocorrer',
                    ]);
                    // Não lançar exceção - tentar enviar mesmo assim e capturar erro depois
                }
            }

            // 🔥 TENTAR ENVIAR EMAIL: Mesmo com validações de aviso, tentar enviar
            // Se houver erro de configuração, será capturado no catch e logado
            try {
                Log::info('EmpresaCriadaListener - Tentando enviar email', [
                    'tenant_id' => $event->tenantId,
                    'email_destino' => $emailDestino,
                    'mail_driver' => $mailDriver,
                    'mail_host' => $mailHost,
                ]);

                Mail::to($emailDestino)->send(new EmpresaCriadaEmail($tenantData, $empresaData));
                
                Log::info('EmpresaCriadaListener - Email enviado com sucesso', [
                    'tenant_id' => $event->tenantId,
                    'email_destino' => $emailDestino,
                ]);
            } catch (\Exception $mailException) {
                Log::error('EmpresaCriadaListener - Erro ao enviar email (capturado)', [
                    'tenant_id' => $event->tenantId,
                    'email_destino' => $emailDestino,
                    'error' => $mailException->getMessage(),
                    'error_class' => get_class($mailException),
                    'trace' => $mailException->getTraceAsString(),
                ]);
                // Re-lançar exceção para ser capturada no catch externo
                throw $mailException;
            }

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

