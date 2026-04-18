<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\LoginAdminDTO;
use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Services\JWTService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Login de Admin
 * 
 * 🔥 DDD: Orquestra login de admin, não conhece HTTP nem banco diretamente
 */
final class LoginAdminUseCase
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $adminUserRepository,
        private readonly JWTService $jwtService,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @throws DomainException Se credenciais inválidas
     */
    public function executar(LoginAdminDTO $dto): array
    {
        Log::info('LoginAdminUseCase::executar - Iniciando login admin', [
            'email' => $dto->email->value,
        ]);

        // Buscar admin através do repository (Domain, não Eloquent)
        $admin = $this->adminUserRepository->buscarPorEmail($dto->email->value);

        // Prevenir timing attacks: sempre verificar senha mesmo se não encontrar
        $isValidPassword = false;
        if ($admin) {
            $isValidPassword = Hash::check($dto->password, $admin->password);
        }

        if (!$admin || !$isValidPassword) {
            Log::warning('LoginAdminUseCase::executar - Credenciais inválidas', [
                'email' => $dto->email->value,
            ]);
            throw new DomainException('Credenciais inválidas.', 401, 'INVALID_CREDENTIALS');
        }

        // Gerar token JWT
        $token = $this->jwtService->generateToken([
            'user_id' => $admin->id,
            'is_admin' => true,
            'role' => 'admin',
        ]);

        Log::info('LoginAdminUseCase::executar - Login admin realizado com sucesso', [
            'admin_id' => $admin->id,
            'email' => $dto->email->value,
        ]);

        return [
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'token' => $token,
            'is_admin' => true,
        ];
    }
}

