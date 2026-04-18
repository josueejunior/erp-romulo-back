<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Obter dados do admin autenticado
 * 
 * 🔥 DDD: Orquestra obtenção de dados do admin
 */
final class ObterDadosAdminUseCase
{
    /**
     * Executar o caso de uso
     */
    public function executar(Authenticatable $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ];
    }
}

