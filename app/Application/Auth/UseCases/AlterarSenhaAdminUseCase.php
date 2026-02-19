<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Domain\Exceptions\DomainException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Alterar Senha do Administrador
 */
final class AlterarSenhaAdminUseCase
{
    /**
     * @param Authenticatable $adminUser
     * @param string $senhaAtual
     * @param string $senhaNova
     * @return void
     */
    public function executar(Authenticatable $adminUser, string $senhaAtual, string $senhaNova): void
    {
        /** @var \App\Modules\Auth\Models\AdminUser $admin */
        $admin = $adminUser;

        // Validar senha atual
        if (!Hash::check($senhaAtual, $admin->password)) {
            throw new DomainException('A senha atual informada está incorreta.', 422);
        }

        // Atualizar senha
        $admin->password = Hash::make($senhaNova);
        $admin->save();

        Log::info('AlterarSenhaAdminUseCase - Senha alterada', [
            'admin_id' => $admin->id
        ]);
    }
}
