<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;
use App\Domain\Shared\ValueObjects\Email;

/**
 * Use Case: Buscar Admin User por Email
 */
class BuscarAdminUserPorEmailUseCase
{
    public function __construct(
        private AdminUserRepositoryInterface $adminUserRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna o modelo AdminUser ou null
     */
    public function executar(string $email): ?\App\Modules\Auth\Models\AdminUser
    {
        // Validar email usando Value Object
        $emailVO = Email::criar($email);
        
        // Buscar admin user através do repository
        return $this->adminUserRepository->buscarPorEmail($emailVO->value);
    }
}



