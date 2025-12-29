<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use DomainException;

/**
 * Use Case: Obter Roles do Usuário Atual
 */
class GetUserRolesUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com as roles do usuário
     */
    public function executar(Authenticatable $user): array
    {
        // Verificar se é modelo Eloquent com método getRoleNames
        if (!method_exists($user, 'getRoleNames')) {
            throw new DomainException('Usuário não possui roles.');
        }

        // Obter roles do usuário
        $roles = $user->getRoleNames()->toArray();

        return [
            'roles' => $roles,
            'primary_role' => !empty($roles) ? $roles[0] : null,
        ];
    }
}

