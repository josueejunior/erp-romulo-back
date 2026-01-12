<?php

namespace App\Application\ProcessoItem\UseCases;

use App\Domain\ProcessoItem\Entities\ProcessoItem;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use DomainException;

/**
 * Use Case: Buscar Item de Processo por ID
 */
class BuscarProcessoItemUseCase
{
    public function __construct(
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoItemId, ?int $processoId, int $empresaId): ProcessoItem
    {
        // Buscar item existente
        $item = $this->processoItemRepository->buscarPorId($processoItemId);
        
        if (!$item) {
            throw new NotFoundException('Item de Processo', $processoItemId);
        }
        
        // Se processoId foi fornecido, validar que o item pertence ao processo
        if ($processoId !== null) {
            // Buscar processo existente
            $processo = $this->processoRepository->buscarPorId($processoId);
            
            if (!$processo) {
                throw new NotFoundException('Processo', $processoId);
            }
            
            // Validar que o processo pertence à empresa
            if ($processo->empresaId !== $empresaId) {
                throw new DomainException('Processo não pertence à empresa ativa.');
            }
            
            // Validar que o item pertence ao processo
            if ($item->processoId !== $processoId) {
                throw new EntidadeNaoPertenceException(
                    'Item de Processo',
                    'Processo',
                    $processoItemId,
                    $processoId
                );
            }
        } else {
            // Validar que o processo do item pertence à empresa
            $processo = $this->processoRepository->buscarPorId($item->processoId);
            
            if (!$processo) {
                throw new NotFoundException('Processo', $item->processoId);
            }
            
            if ($processo->empresaId !== $empresaId) {
                throw new DomainException('Item não pertence à empresa ativa.');
            }
        }
        
        return $item;
    }
}





