<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Notifications\ResetPasswordNotification;

/**
 * Use Case para solicitar reset de senha
 * 
 * Responsabilidades:
 * - Buscar usuário em todos os tenants (multi-tenancy)
 * - Gerar token de reset
 * - Enviar notificação
 * - Prevenir enumeração de emails
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
     * @return bool Sempre retorna true (prevenir enumeração)
     */
    public function executar(string $email): bool
    {
        $userFound = false;

        // 1. Tentar buscar no banco central (admin users)
        $adminUser = $this->adminUserRepository->buscarPorEmail($email);
        if ($adminUser) {
            $userFound = true;
            // Para admin, usar o sistema padrão do Laravel (se configurado)
            // Por enquanto, vamos usar a mesma abordagem para todos
        }

        // 2. Buscar em todos os tenants (multi-tenancy)
        if (!$userFound) {
            // Buscar todos os tenants (per_page alto)
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
            $tenants = $tenantsPaginator->getCollection();
            
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
                        tenancy()->end(); // Finalizar tenancy antes de criar token
                        
                        // Gerar token
                        $token = Str::random(64);
                        $hashedToken = Hash::make($token);
                        
                        // Salvar token no banco central
                        DB::connection()->table('password_reset_tokens')
                            ->updateOrInsert(
                                ['email' => $email],
                                [
                                    'token' => $hashedToken,
                                    'created_at' => now(),
                                ]
                            );
                        
                        // Buscar modelo Eloquent para enviar notificação
                        $userModel = \App\Modules\Auth\Models\User::where('email', $email)->first();
                        if ($userModel) {
                            $userModel->notify(new ResetPasswordNotification($token));
                        }
                        
                        break;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                    Log::warning('Erro ao buscar usuário no tenant para reset de senha', [
                        'tenant_id' => $tenant->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Sempre retornar true para prevenir enumeração de emails
        return true;
    }
}

