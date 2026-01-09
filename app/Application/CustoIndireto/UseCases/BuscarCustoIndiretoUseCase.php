<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Buscar Custo Indireto por ID
 */
class BuscarCustoIndiretoUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $custoIndiretoId, int $empresaId): CustoIndireto
    {
        // Buscar custo indireto
        $custo = $this->custoIndiretoRepository->buscarPorId($custoIndiretoId);
        
        if (!$custo) {
            throw new NotFoundException('Custo Indireto', $custoIndiretoId);
        }
        
        // Validar que o custo indireto pertence à empresa
        if ($custo->empresaId !== $empresaId) {
            throw new DomainException('Custo indireto não pertence à empresa ativa.');
        }
        
        return $custo;
    }
}




