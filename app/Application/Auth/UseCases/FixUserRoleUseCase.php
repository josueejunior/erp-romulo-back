<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\UserRoleServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use DomainException;

/**
 * Use Case: Corrigir Role do Usuário Atual
 */
class FixUserRoleUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserRoleServiceInterface $roleService,
    ) {}

    /**
     * Executar o caso de uso
     * Corrige a role do usuário baseado na empresa ativa
     */
    public function executar(Authenticatable $user, ?string $role = null): array
    {
        // Verificar se é modelo Eloquent
        if (!method_exists($user, 'empresas')) {
            throw new DomainException('Usuário não possui empresas associadas.');
        }

        // Obter empresa ativa
        $empresaAtiva = null;
        if (method_exists($user, 'empresa_ativa_id') && $user->empresa_ativa_id) {
            $empresas = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->get();
            $empresaAtiva = $empresas->first();
        } else {
            $empresaAtiva = $user->empresas()->first();
        }

        if (!$empresaAtiva) {
            throw new DomainException('Usuário não possui empresa associada.');
        }

        // Obter perfil da empresa (pivot)
        $perfil = $empresaAtiva->pivot->perfil ?? 'consulta';

        // Mapear perfil para role
        $roleMap = [
            'administrador' => 'Administrador',
            'operacional' => 'Operacional',
            'financeiro' => 'Financeiro',
            'consulta' => 'Consulta',
        ];

        $targetRole = $role ?? $roleMap[strtolower($perfil)] ?? 'Consulta';

        // Buscar usuário do domínio
        $userDomain = $this->userRepository->buscarPorId($user->id);
        if (!$userDomain) {
            throw new DomainException('Usuário não encontrado.');
        }

        // Atribuir role usando Domain Service
        $this->roleService->sincronizarRoles($userDomain, [$targetRole]);

        // Obter roles atualizadas
        $user->refresh();
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];

        return [
            'message' => 'Role corrigida com sucesso!',
            'role' => $targetRole,
            'roles' => $roles,
            'perfil_empresa' => $perfil,
        ];
    }
}

