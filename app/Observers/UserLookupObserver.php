<?php

namespace App\Observers;

use App\Modules\Auth\Models\User;
use App\Application\CadastroPublico\Services\UsersLookupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Observer para manter users_lookup sincronizado automaticamente
 * 
 * 🔥 ARQUITETURA: Sempre que um usuário é criado/atualizado/deletado,
 * este observer atualiza a tabela users_lookup para manter consistência.
 */
class UserLookupObserver
{
    public function __construct(
        private UsersLookupService $lookupService,
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Só atualizar se estiver em contexto de tenant
        if (!tenancy()->initialized || !tenancy()->tenant) {
            return;
        }

        // Garantir que temos o ID do tenant (Stancl/Tenancy pode retornar vazio em contextos de seed)
        $tenantId = tenancy()->tenant->id ?? tenancy()->tenant->getAttribute('id');
        
        if (empty($tenantId)) {
            Log::warning('UserLookupObserver: Ignorando sincronização pois tenantId está vazio', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            return;
        }

        $tenantId = (int) $tenantId;
        
        try {
            // Buscar empresas do usuário
            $empresas = $user->empresas()->withoutTrashed()->get();
            
            if ($empresas->isEmpty()) {
                // Se não tem empresa, usar CNPJ do tenant como fallback
                $cnpjLimpo = preg_replace('/\D/', '', tenancy()->tenant->cnpj ?? '');
                
                if (!empty($user->email) && !empty($cnpjLimpo) && !empty($user->id)) {
                    $this->lookupService->registrar(
                        tenantId: $tenantId,
                        userId: (int) $user->id,
                        empresaId: null,
                        email: $user->email,
                        cnpj: $cnpjLimpo,
                    );
                }
            } else {
                // Para cada empresa, criar registro
                foreach ($empresas as $empresa) {
                    $cnpjLimpo = preg_replace('/\D/', '', $empresa->cnpj ?? tenancy()->tenant->cnpj ?? '');
                    
                    if (empty($cnpjLimpo) || empty($user->email) || empty($user->id)) {
                        continue;
                    }
                    
                    $this->lookupService->registrar(
                        tenantId: $tenantId,
                        userId: (int) $user->id,
                        empresaId: (int) $empresa->id,
                        email: $user->email,
                        cnpj: $cnpjLimpo,
                    );
                }
            }
            
            Log::debug('UserLookupObserver: Registro criado na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            // Não falhar criação do usuário se lookup falhar
            Log::warning('UserLookupObserver: Erro ao criar registro na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Só atualizar se estiver em contexto de tenant
        if (!tenancy()->initialized || !tenancy()->tenant) {
            return;
        }

        // Garantir que temos o ID do tenant
        $tenantId = tenancy()->tenant->id ?? tenancy()->tenant->getAttribute('id');
        
        if (empty($tenantId)) {
            return;
        }

        $tenantId = (int) $tenantId;
        
        try {
            // Se email mudou, atualizar lookup
            if ($user->wasChanged('email')) {
                // Buscar empresas do usuário
                $empresas = $user->empresas()->withoutTrashed()->get();
                
                if ($empresas->isEmpty()) {
                    $cnpjLimpo = preg_replace('/\D/', '', tenancy()->tenant->cnpj ?? '');
                    
                    if (!empty($user->email) && !empty($cnpjLimpo) && !empty($user->id)) {
                        $this->lookupService->registrar(
                            tenantId: $tenantId,
                            userId: (int) $user->id,
                            empresaId: null,
                            email: $user->email,
                            cnpj: $cnpjLimpo,
                        );
                    }
                } else {
                    foreach ($empresas as $empresa) {
                        $cnpjLimpo = preg_replace('/\D/', '', $empresa->cnpj ?? tenancy()->tenant->cnpj ?? '');
                        
                        if (empty($cnpjLimpo) || empty($user->email) || empty($user->id)) {
                            continue;
                        }
                        
                        $this->lookupService->registrar(
                            tenantId: $tenantId,
                            userId: (int) $user->id,
                            empresaId: (int) $empresa->id,
                            email: $user->email,
                            cnpj: $cnpjLimpo,
                        );
                    }
                }
            }
            
            Log::debug('UserLookupObserver: Registro atualizado na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::warning('UserLookupObserver: Erro ao atualizar registro na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Só atualizar se estiver em contexto de tenant
        if (!tenancy()->initialized || !tenancy()->tenant) {
            return;
        }

        $tenantId = tenancy()->tenant->id;
        
        try {
            // Marcar como inativo na users_lookup (soft delete)
            $lookupRepository = app(\App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface::class);
            $lookups = $lookupRepository->buscarAtivosPorEmail($user->email);
            
            foreach ($lookups as $lookup) {
                if ($lookup->tenantId === $tenantId && $lookup->userId === $user->id) {
                    $lookupRepository->marcarComoInativo($lookup->id);
                }
            }
            
            Log::debug('UserLookupObserver: Registro marcado como inativo na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::warning('UserLookupObserver: Erro ao marcar registro como inativo na users_lookup', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        // Quando usuário é restaurado, recriar registro na lookup
        $this->created($user);
    }
}

