<?php

namespace App\Application\ProcessoItem\UseCases;

use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\ProcessoEmExecucaoException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use DomainException;

/**
 * Use Case: Excluir Item de Processo
 */
class ExcluirProcessoItemUseCase
{
    public function __construct(
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoItemId, int $processoId, int $empresaId): void
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
        
        // Buscar item existente
        $item = $this->processoItemRepository->buscarPorId($processoItemId);
        
        if (!$item) {
            throw new NotFoundException('Item de Processo', $processoItemId);
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
        
        // Validar regra de negócio: processo não pode estar em execução
        if ($processo->estaEmExecucao()) {
            throw new ProcessoEmExecucaoException('Não é possível excluir itens de processos em execução.', $processoId);
        }
        
        // Deletar item
        $this->processoItemRepository->deletar($processoItemId);
    }
}









