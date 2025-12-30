<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;
use App\Modules\Auth\Models\AdminUser;

/**
 * Implementação do Repository de AdminUser usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class AdminUserRepository implements AdminUserRepositoryInterface
{
    public function buscarPorEmail(string $email): ?AdminUser
    {
        return AdminUser::where('email', $email)->first();
    }

    public function buscarPorId(int $id): ?AdminUser
    {
        return AdminUser::find($id);
    }
}

