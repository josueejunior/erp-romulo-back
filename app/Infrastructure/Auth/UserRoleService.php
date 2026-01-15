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
        // 🔥 CORREÇÃO: Dentro de transações, pode haver problemas de visibilidade
        // Usar where() ao invés de find() para garantir busca no contexto correto
        $model = UserModel::where('id', $user->id)->first();
        
        if (!$model) {
            // Tentar novamente com refresh da conexão (pode ser problema de timing)
            \DB::connection()->reconnect();
            $model = UserModel::where('id', $user->id)->first();
        }
        
        if (!$model) {
            throw new \RuntimeException("Usuário com ID {$user->id} não encontrado para atribuir role. Verifique se o usuário foi criado corretamente.");
        }
        
        $model->assignRole($role);
    }

    public function removerRole(User $user, string $role): void
    {
        $model = UserModel::find($user->id);
        
        if (!$model) {
            $model = UserModel::where('id', $user->id)->first();
        }
        
        if (!$model) {
            throw new \RuntimeException("Usuário com ID {$user->id} não encontrado para remover role.");
        }
        
        $model->removeRole($role);
    }

    public function sincronizarRoles(User $user, array $roles): void
    {
        $model = UserModel::find($user->id);
        
        if (!$model) {
            $model = UserModel::where('id', $user->id)->first();
        }
        
        if (!$model) {
            throw new \RuntimeException("Usuário com ID {$user->id} não encontrado para sincronizar roles.");
        }
        
        $model->syncRoles($roles);
    }

    public function temRole(User $user, string $role): bool
    {
        $model = UserModel::find($user->id);
        
        if (!$model) {
            $model = UserModel::where('id', $user->id)->first();
        }
        
        if (!$model) {
            return false;
        }
        
        return $model->hasRole($role);
    }
}




