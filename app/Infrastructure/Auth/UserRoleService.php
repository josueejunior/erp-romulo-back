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
        // 🔥 CORREÇÃO CRÍTICA: O Global Scope do User filtra por whereHas('empresas')
        // Quando o usuário é recém-criado, ainda não tem empresas vinculadas,
        // então o scope filtra ele fora!
        
        // Verificar se existe no banco primeiro
        $exists = \DB::table('users')->where('id', $user->id)->exists();
        
        if (!$exists) {
            throw new \RuntimeException("Usuário com ID {$user->id} não encontrado para atribuir role.");
        }
        
        // 🔥 SOLUÇÃO: Buscar dados do banco e criar modelo manualmente
        // Isso evita o Global Scope que filtra usuários sem empresas
        $userData = \DB::table('users')->where('id', $user->id)->first();
        
        if (!$userData) {
            throw new \RuntimeException("Usuário com ID {$user->id} não encontrado no banco.");
        }
        
        // Criar instância do modelo a partir dos dados do banco
        // Isso bypassa o Global Scope porque não passa pela query builder
        $model = (new UserModel())->newFromBuilder($userData);
        
        // Agora podemos usar assignRole normalmente
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




