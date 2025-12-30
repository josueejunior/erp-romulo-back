<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Use Case: Buscar Assinatura Atual do Tenant
 * Orquestra a busca da assinatura atual de um tenant
 */
class BuscarAssinaturaAtualUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @return Assinatura
     * @throws NotFoundException Se a assinatura não for encontrada
     */
    public function executar(int $tenantId): Assinatura
    {
        $assinatura = $this->assinaturaRepository->buscarAssinaturaAtual($tenantId);

        if (!$assinatura) {
            throw new NotFoundException("Nenhuma assinatura encontrada para este tenant.");
        }

        return $assinatura;
    }
}

