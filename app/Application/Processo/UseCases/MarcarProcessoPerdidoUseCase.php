<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Marcar Processo como Perdido
 * 
 * ⚠️ NOTA: Ainda usa ProcessoStatusService que trabalha com modelos Eloquent.
 * Idealmente, esta lógica deveria estar na entidade Processo, mas mantemos Service por compatibilidade.
 */
class MarcarProcessoPerdidoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoStatusService $statusService,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $processoId
     * @param int $empresaId
     * @param string|null $motivoPerda Anotações sobre o motivo da perda
     */
    public function executar(int $processoId, int $empresaId, ?string $motivoPerda = null): \App\Modules\Processo\Models\Processo
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
        $result = $this->statusService->alterarStatus($processoModel, 'perdido');
        
        if (!$result['pode']) {
            throw new DomainException($result['motivo']);
        }

        // Salvar motivo da perda se fornecido
        if ($motivoPerda !== null) {
            $processoModel->motivo_perda = $motivoPerda;
            $processoModel->save();
        }

        return $processoModel->fresh(['orgao', 'setor']);
    }
}









