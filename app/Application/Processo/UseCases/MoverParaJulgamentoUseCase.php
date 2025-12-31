<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use DomainException;

/**
 * Use Case: Mover Processo para Julgamento
 */
class MoverParaJulgamentoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoId): Processo
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processo) {
            throw new DomainException('Processo não encontrado.');
        }

        // Aplicar regra de negócio (mover para julgamento)
        $processoAtualizado = $processo->moverParaJulgamento();

        // Persistir alteração
        return $this->processoRepository->atualizar($processoAtualizado);
    }
}


