<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Entities\User;

/**
 * Domain Service: Gerenciamento de Roles de Usuário
 * Regras que envolvem múltiplas entidades ou lógica complexa
 */
interface UserRoleServiceInterface
{
    /**
     * Atribuir role a um usuário
     */
    public function atribuirRole(User $user, string $role): void;

    /**
     * Remover role de um usuário
     */
    public function removerRole(User $user, string $role): void;

    /**
     * Sincronizar roles de um usuário
     */
    public function sincronizarRoles(User $user, array $roles): void;

    /**
     * Verificar se usuário tem role
     */
    public function temRole(User $user, string $role): bool;
}

