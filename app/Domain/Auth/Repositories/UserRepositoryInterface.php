<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\User;

/**
 * Interface do Repository de User
 */
interface UserRepositoryInterface
{
    /**
     * Criar usuário administrador no tenant
     */
    public function criarAdministrador(
        int $tenantId,
        int $empresaId,
        string $nome,
        string $email,
        string $senha
    ): User;
}

