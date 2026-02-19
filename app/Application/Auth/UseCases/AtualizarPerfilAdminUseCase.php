<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\AdminUserRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Atualizar Perfil do Administrador
 */
final class AtualizarPerfilAdminUseCase
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $adminUserRepository
    ) {}

    /**
     * @param Authenticatable $adminUser
     * @param array $data ['name', 'email']
     * @return array
     */
    public function executar(Authenticatable $adminUser, array $data): array
    {
        // Verificar se email já está em uso por outro admin
        $existing = $this->adminUserRepository->buscarPorEmail($data['email']);
        if ($existing && $existing->id !== $adminUser->id) {
            throw new DomainException('Este email já está sendo utilizado por outro administrador.', 422);
        }

        /** @var \App\Modules\Auth\Models\AdminUser $admin */
        $admin = $adminUser;
        $admin->name = $data['name'];
        $admin->email = $data['email'];
        
        // Salvar via Eloquent por enquanto (já que Repository só busca)
        $admin->save();

        Log::info('AtualizarPerfilAdminUseCase - Perfil atualizado', [
            'admin_id' => $admin->id,
            'email' => $admin->email
        ]);

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'created_at' => $admin->created_at,
        ];
    }
}
