<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;

use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Notifications\ResetPasswordNotification;

/**
 * Use Case para solicitar reset de senha
 * 
 * Responsabilidades:
 * - Validar se email existe no sistema
 * - Buscar usuário em todos os tenants (multi-tenancy)
 * - Gerar token de reset
 * - Enviar notificação via SMTP
 * 
 * 🔥 IMPORTANTE: Valida se email existe antes de enviar (segurança)
 */
class SolicitarResetSenhaUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminUserRepositoryInterface $adminUserRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @param string $email Email do usuário
     * @return array Array com 'success' e 'message'
     * @throws DomainException Se email não existir
     */
    public function executar(string $email): array
    {
        $email = strtolower(trim($email));
        $userFound = false;
        $userModel = null;
        $tenantFound = null;

        Log::info('SolicitarResetSenhaUseCase - Iniciando busca de usuário', ['email' => $email]);

        // 1. Tentar buscar no banco central (admin users)
        try {
            $adminUser = $this->adminUserRepository->buscarPorEmail($email);
            if ($adminUser) {
                $userFound = true;
                Log::info('SolicitarResetSenhaUseCase - Usuário admin encontrado', ['email' => $email]);
                // Para admin, criar token e enviar email
                $token = Str::random(64);
                $hashedToken = Hash::make($token);
                
                DB::connection()->table('password_reset_tokens')
                    ->updateOrInsert(
                        ['email' => $email],
                        [
                            'token' => $hashedToken,
                            'created_at' => now(),
                        ]
                    );
                
                // Enviar email para admin (via Mail facade direto já que não temos modelo User para admin)
                try {
                    Mail::raw(
                        "Você solicitou uma redefinição de senha.\n\n" .
                        "Clique no link abaixo para redefinir sua senha:\n" .
                        config('app.frontend_url', env('FRONTEND_URL', 'https://gestor.addsimp.com')) . 
                        "/resetar-senha?token={$token}&email=" . urlencode($email) . "\n\n" .
                        "Este link expira em 60 minutos.\n\n" .
                        "Se você não solicitou esta redefinição, ignore este e-mail.",
                        function ($message) use ($email) {
                            $message->to($email)
                                ->subject('Redefinição de Senha - Sistema ERP');
                        }
                    );
                    
                    Log::info('SolicitarResetSenhaUseCase - Email enviado para admin via SMTP', ['email' => $email]);
                } catch (\Exception $mailError) {
                    Log::error('SolicitarResetSenhaUseCase - Erro ao enviar email para admin', [
                        'email' => $email,
                        'error' => $mailError->getMessage(),
                    ]);
                    throw new DomainException('Erro ao enviar email de redefinição de senha. Tente novamente mais tarde.', 500);
                }
                
                return [
                    'success' => true,
                    'message' => 'Instruções de redefinição de senha enviadas para seu e-mail.',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('SolicitarResetSenhaUseCase - Erro ao buscar admin user', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Buscar em todos os tenants (multi-tenancy)
        try {
            // Buscar todos os tenants (per_page alto)
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
            $tenants = $tenantsPaginator->getCollection();
            
            Log::info('SolicitarResetSenhaUseCase - Buscando em tenants', [
                'email' => $email,
                'total_tenants' => $tenants->count(),
            ]);
            
            foreach ($tenants as $tenantDomain) {
                try {
                    // Buscar modelo Eloquent para inicializar tenancy
                    $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    if (!$tenant) {
                        continue;
                    }
                    
                    tenancy()->initialize($tenant);
                    
                    $user = $this->userRepository->buscarPorEmail($email);
                    
                    if ($user) {
                        $userFound = true;
                        $tenantFound = $tenant;
                        
                        // Buscar modelo Eloquent para enviar notificação
                        $userModel = \App\Modules\Auth\Models\User::where('email', $email)->first();
                        
                        Log::info('SolicitarResetSenhaUseCase - Usuário encontrado no tenant', [
                            'email' => $email,
                            'tenant_id' => $tenant->id,
                            'user_id' => $userModel?->id,
                        ]);
                        
                        break;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                    Log::warning('SolicitarResetSenhaUseCase - Erro ao buscar usuário no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('SolicitarResetSenhaUseCase - Erro ao buscar em tenants', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            throw new DomainException('Erro ao processar solicitação. Tente novamente mais tarde.', 500);
        }

        // Se não encontrou usuário, retornar erro
        if (!$userFound || !$userModel) {
            Log::info('SolicitarResetSenhaUseCase - Email não encontrado no sistema', ['email' => $email]);
            throw new DomainException('O e-mail informado não está cadastrado no sistema.', 404);
        }

        // Gerar token de reset
        $token = Str::random(64);
        $hashedToken = Hash::make($token);
        
        try {
            // Finalizar tenancy antes de criar token no banco central
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            
            // Salvar token no banco central
            DB::connection()->table('password_reset_tokens')
                ->updateOrInsert(
                    ['email' => $email],
                    [
                        'token' => $hashedToken,
                        'created_at' => now(),
                    ]
                );
            
            // Reinicializar tenancy para enviar notificação
            if ($tenantFound) {
                tenancy()->initialize($tenantFound);
            }
            
            // Log da configuração SMTP antes de enviar (sem expor senha)
            Log::info('SolicitarResetSenhaUseCase - Tentando enviar email via SMTP', [
                'email' => $email,
                'tenant_id' => $tenantFound?->id,
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_username' => config('mail.mailers.smtp.username'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'has_password' => !empty(config('mail.mailers.smtp.password')),
            ]);
            
            // Enviar notificação via SMTP usando Laravel Notifications
            // Isso garante que o email será enviado usando a configuração SMTP
            $userModel->notify(new ResetPasswordNotification($token));
            
            Log::info('SolicitarResetSenhaUseCase - Email de reset enviado com sucesso via SMTP', [
                'email' => $email,
                'tenant_id' => $tenantFound?->id,
            ]);
            
            // Finalizar tenancy após enviar email
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Garantir que tenancy seja finalizado em caso de erro
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            
            Log::error('SolicitarResetSenhaUseCase - Erro de transporte SMTP ao enviar email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_username' => config('mail.mailers.smtp.username'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'error_class' => get_class($e),
            ]);
            
            // Mensagem mais específica baseada no tipo de erro
            $errorMessage = 'Erro ao enviar email de redefinição de senha. ';
            if (str_contains($e->getMessage(), 'Client host rejected') || str_contains($e->getMessage(), 'Access denied')) {
                $errorMessage .= 'O servidor SMTP está rejeitando a conexão. Verifique se o IP do servidor está autorizado e se as credenciais SMTP estão corretas.';
            } elseif (str_contains($e->getMessage(), 'authentication') || str_contains($e->getMessage(), 'login')) {
                $errorMessage .= 'Erro de autenticação SMTP. Verifique as credenciais (usuário e senha) no arquivo .env.';
            } else {
                $errorMessage .= 'Verifique as configurações de SMTP no servidor ou entre em contato com o suporte.';
            }
            
            throw new DomainException($errorMessage, 500);
        } catch (\Exception $e) {
            // Garantir que tenancy seja finalizado em caso de erro
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            
            Log::error('SolicitarResetSenhaUseCase - Erro genérico ao gerar token ou enviar email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new DomainException('Erro ao enviar email de redefinição de senha. Tente novamente mais tarde ou entre em contato com o suporte.', 500);
        }

        return [
            'success' => true,
            'message' => 'Instruções de redefinição de senha enviadas para seu e-mail.',
        ];
    }
}

