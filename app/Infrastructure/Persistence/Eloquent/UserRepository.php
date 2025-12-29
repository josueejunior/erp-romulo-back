<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Support\Facades\Hash;

/**
 * Implementação do Repository de User usando Eloquent
 */
class UserRepository implements UserRepositoryInterface
{
    public function criarAdministrador(
        int $tenantId,
        int $empresaId,
        string $nome,
        string $email,
        string $senha
    ): User {
        $model = UserModel::create([
            'name' => $nome,
            'email' => $email,
            'password' => Hash::make($senha),
            'empresa_ativa_id' => $empresaId,
        ]);

        // Atribuir role de Administrador
        $model->assignRole('Administrador');

        // Associar usuário à empresa
        $model->empresas()->attach($empresaId, [
            'perfil' => 'administrador'
        ]);

        return new User(
            id: $model->id,
            tenantId: $tenantId,
            nome: $model->name,
            email: $model->email,
            senhaHash: $model->password,
            empresaAtivaId: $model->empresa_ativa_id,
        );
    }
}

