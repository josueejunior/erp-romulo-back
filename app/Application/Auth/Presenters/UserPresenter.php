<?php

namespace App\Application\Auth\Presenters;

use App\Domain\Auth\Entities\User;
use App\Modules\Auth\Models\User as UserModel;

/**
 * Presenter: Transforma entidades de domínio em arrays para resposta
 * Controller nunca conhece estrutura interna do domínio
 */
class UserPresenter
{
    /**
     * Transformar entidade de domínio para array de resposta
     */
    public static function fromDomain(User $user, ?UserModel $userModel = null): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->nome,
            'email' => $user->email,
            'empresa_ativa_id' => $user->empresaAtivaId,
        ];

        // Se modelo Eloquent fornecido, adicionar dados de relacionamentos
        if ($userModel) {
            $data['roles'] = $userModel->roles->pluck('name')->toArray();
            $data['empresas'] = $userModel->empresas->map(fn($e) => [
                'id' => $e->id,
                'razao_social' => $e->razao_social,
            ])->toArray();
        }

        return $data;
    }

    /**
     * Transformar coleção de usuários
     */
    public static function collection(iterable $users): array
    {
        return array_map(fn($user) => self::fromDomain($user), $users);
    }
}




