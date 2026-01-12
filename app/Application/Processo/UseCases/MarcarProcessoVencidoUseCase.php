<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Marcar Processo como Vencido
 * 
 * ⚠️ NOTA: Ainda usa ProcessoStatusService que trabalha com modelos Eloquent.
 * Idealmente, esta lógica deveria estar na entidade Processo, mas mantemos Service por compatibilidade.
 */
class MarcarProcessoVencidoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoStatusService $statusService,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoId, int $empresaId): \App\Modules\Processo\Models\Processo
    {
        // Buscar processo (domain entity)
        $processoDomain = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processoDomain) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processoDomain->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Buscar modelo Eloquent para ProcessoStatusService
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId, ['itens']);
        
        if (!$processoModel) {
            throw new NotFoundException('Processo', $processoId);
        }

        // Usar ProcessoStatusService para alterar status (ainda trabalha com Eloquent)
        $result = $this->statusService->alterarStatus($processoModel, 'execucao');
        
        if (!$result['pode']) {
            throw new DomainException($result['motivo']);
        }

        return $processoModel->fresh(['orgao', 'setor']);
    }
}






