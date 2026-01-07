<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Deletar Plano
 */
class DeletarPlanoUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $id ID do plano
     * @return void
     */
    public function executar(int $id): void
    {
        // Buscar plano existente
        $plano = $this->planoRepository->buscarPorId($id);
        
        if (!$plano) {
            throw new NotFoundException("Plano não encontrado.");
        }

        // Verificar se há assinaturas ativas usando este plano
        $modelo = $this->planoRepository->buscarModeloPorId($id);
        if ($modelo && $modelo->assinaturas()->where('status', 'ativa')->count() > 0) {
            throw new DomainException("Não é possível deletar um plano que possui assinaturas ativas. Desative o plano ao invés de deletá-lo.");
        }

        // Deletar plano
        $this->planoRepository->deletar($id);
    }
}



