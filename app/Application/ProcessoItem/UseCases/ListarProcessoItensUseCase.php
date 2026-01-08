<?php

namespace App\Application\ProcessoItem\UseCases;

use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Listar Itens de Processo
 */
class ListarProcessoItensUseCase
{
    public function __construct(
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @return array Array de entidades ProcessoItem
     */
    public function executar(int $processoId, int $empresaId): array
    {
        // Buscar processo existente
        $processo = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processo->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Buscar itens do processo
        return $this->processoItemRepository->buscarPorProcesso($processoId);
    }
}

