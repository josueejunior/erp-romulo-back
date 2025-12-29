<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Obter Dados do Usuário Autenticado
 */
class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant e empresa
     */
    public function executar(Authenticatable $user): array
    {
        // Obter tenant do token
        $tenant = null;
        $tenantId = null;
        
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $abilities = $user->currentAccessToken()->abilities;
            $tenantId = $abilities['tenant_id'] ?? null;
        }
        
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        }

        // Buscar empresa ativa
        $empresaAtiva = null;
        if ($tenant) {
            tenancy()->initialize($tenant);
            try {
                if (method_exists($user, 'empresa_ativa_id') && $user->empresa_ativa_id) {
                    $empresaAtiva = $this->userRepository->buscarEmpresaAtiva($user->id);
                } else {
                    // Se não tem empresa ativa, buscar primeira empresa
                    $empresas = $this->userRepository->buscarEmpresas($user->id);
                    $empresaAtiva = !empty($empresas) ? $empresas[0] : null;
                }
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => method_exists($user, 'name') ? $user->name : null,
                'email' => $user->getAuthIdentifierName() === 'email' ? $user->email : null,
                'empresa_ativa_id' => method_exists($user, 'empresa_ativa_id') ? $user->empresa_ativa_id : null,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
            ] : null,
            'empresa' => $empresaAtiva ? [
                'id' => $empresaAtiva->id,
                'razao_social' => $empresaAtiva->razaoSocial,
            ] : null,
        ];
    }
}

