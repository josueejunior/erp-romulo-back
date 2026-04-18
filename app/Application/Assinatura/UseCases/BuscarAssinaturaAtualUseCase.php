<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Use Case: Buscar Assinatura Atual do Usuário
 * Orquestra a busca da assinatura atual de um usuário
 * 
 * 🔥 NOVO: Assinatura pertence ao usuário, não ao tenant
 */
class BuscarAssinaturaAtualUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $userId ID do usuário
     * @return Assinatura
     * @throws NotFoundException Se a assinatura não for encontrada
     */
    public function executar(int $userId): Assinatura
    {
        $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorUsuario($userId);

        if (!$assinatura) {
            throw new NotFoundException("Nenhuma assinatura encontrada para este usuário.");
        }

        return $assinatura;
    }
}

