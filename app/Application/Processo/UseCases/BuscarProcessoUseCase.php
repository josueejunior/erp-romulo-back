<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Buscar Processo por ID
 */
class BuscarProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoId, int $empresaId): Processo
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processo->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        return $processo;
    }
}



