<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Excluir Custo Indireto
 */
class ExcluirCustoIndiretoUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $custoIndiretoId, int $empresaId): void
    {
        // Buscar custo indireto existente
        $custo = $this->custoIndiretoRepository->buscarPorId($custoIndiretoId);
        
        if (!$custo) {
            throw new NotFoundException('Custo Indireto', $custoIndiretoId);
        }
        
        // Validar que o custo indireto pertence à empresa
        if ($custo->empresaId !== $empresaId) {
            throw new DomainException('Custo indireto não pertence à empresa ativa.');
        }
        
        // Deletar custo indireto
        $this->custoIndiretoRepository->deletar($custoIndiretoId);
    }
}



