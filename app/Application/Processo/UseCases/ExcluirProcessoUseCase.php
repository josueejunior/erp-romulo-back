<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Excluir Processo
 */
class ExcluirProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoId, int $empresaId): void
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
        
        // Validar regras de negócio (ex: não pode excluir se tiver empenhos vinculados)
        // Esta validação pode ser feita aqui ou no Repository
        
        // Deletar processo (soft delete)
        $this->processoRepository->deletar($processoId);
    }
}

