<?php

namespace App\Infrastructure\Auth;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Services\UserRoleServiceInterface;
use App\Modules\Auth\Models\User as UserModel;

/**
 * Implementação do Domain Service de Roles
 * Conhece detalhes de infraestrutura (Spatie Permission)
 */
class UserRoleService implements UserRoleServiceInterface
{
    public function atribuirRole(User $user, string $role): void
    {
        $model = UserModel::findOrFail($user->id);
        $model->assignRole($role);
    }

    public function removerRole(User $user, string $role): void
    {
        $model = UserModel::findOrFail($user->id);
        $model->removeRole($role);
    }

    public function sincronizarRoles(User $user, array $roles): void
    {
        $model = UserModel::findOrFail($user->id);
        $model->syncRoles($roles);
    }

    public function temRole(User $user, string $role): bool
    {
        $model = UserModel::findOrFail($user->id);
        return $model->hasRole($role);
    }
}




