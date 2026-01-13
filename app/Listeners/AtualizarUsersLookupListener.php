<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Tenant\Events\EmpresaCriada;
use App\Application\CadastroPublico\Services\UsersLookupService;
use App\Services\AdminTenancyRunner;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Listener para evento EmpresaCriada
 * Atualiza a tabela users_lookup quando uma empresa é criada
 * 
 * ⚡ Event-Driven: Desacopla a lógica de sincronização do Use Case principal
 */
class AtualizarUsersLookupListener
{
    public function __construct(
        private readonly UsersLookupService $usersLookupService,
        private readonly AdminTenancyRunner $adminTenancyRunner,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}
    
    /**
     * Handle the event.
     */
    public function handle(EmpresaCriada $event): void
    {
        Log::info('AtualizarUsersLookupListener::handle iniciado', [
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
            'email' => $event->email,
            'userId' => $event->userId,
        ]);

        try {
            // Validar dados mínimos
            if (!$event->email || !$event->cnpj) {
                Log::warning('AtualizarUsersLookupListener - Email ou CNPJ não disponível', [
                    'tenant_id' => $event->tenantId,
                    'email' => $event->email,
                    'cnpj' => $event->cnpj,
                ]);
                return;
            }

            $userId = $event->userId;
            $tenantDomain = $this->tenantRepository->buscarPorId($event->tenantId);
            
            if (!$tenantDomain) {
                Log::warning('AtualizarUsersLookupListener - Tenant não encontrado', [
                    'tenant_id' => $event->tenantId,
                ]);
                return;
            }

            // Se userId não foi fornecido no evento, buscar pelo email no tenant
            if (!$userId) {
                Log::debug('AtualizarUsersLookupListener - Buscando userId pelo email', [
                    'tenant_id' => $event->tenantId,
                    'email' => $event->email,
                ]);

                $user = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($event) {
                    return $this->userRepository->buscarPorEmail($event->email);
                });

                if (!$user) {
                    Log::warning('AtualizarUsersLookupListener - Usuário não encontrado no tenant', [
                        'tenant_id' => $event->tenantId,
                        'email' => $event->email,
                    ]);
                    return;
                }

                $userId = $user->id;
                
                Log::info('AtualizarUsersLookupListener - Usuário encontrado', [
                    'tenant_id' => $event->tenantId,
                    'user_id' => $userId,
                    'email' => $event->email,
                ]);
            }

            // Registrar na tabela users_lookup
            $this->usersLookupService->registrar(
                tenantId: $event->tenantId,
                userId: $userId,
                empresaId: $event->empresaId,
                email: $event->email,
                cnpj: $event->cnpj
            );

            Log::info('AtualizarUsersLookupListener::handle concluído com sucesso', [
                'tenant_id' => $event->tenantId,
                'user_id' => $userId,
                'empresa_id' => $event->empresaId,
                'email' => $event->email,
            ]);

        } catch (\Exception $e) {
            // Não quebrar o fluxo se houver erro ao atualizar users_lookup
            Log::error('AtualizarUsersLookupListener - Erro ao atualizar users_lookup', [
                'tenant_id' => $event->tenantId,
                'empresa_id' => $event->empresaId,
                'email' => $event->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}





