<?php

namespace App\Application\Empenho\UseCases;

use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use DomainException;

/**
 * Use Case: Concluir Empenho
 */
class ConcluirEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(int $empenhoId, int $empresaId, ?int $processoId = null): Empenho
    {
        $empenho = $this->empenhoRepository->buscarPorId($empenhoId);
        
        if (!$empenho) {
            throw new DomainException('Empenho não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($empenho->empresaId !== $empresaId) {
            throw new DomainException('Empenho não pertence à empresa ativa.');
        }

        // Validar que pertence ao processo se fornecido
        if ($processoId && $empenho->processoId !== $processoId) {
            throw new DomainException('Empenho não pertence ao processo.');
        }

        // Garantir idempotência: verificar se já está concluído
        if ($empenho->concluido) {
            // Retornar o empenho já concluído (idempotente)
            return $empenho;
        }

        // Concluir empenho (regra de domínio)
        $empenhoConcluido = $empenho->concluir();

        return $this->empenhoRepository->atualizar($empenhoConcluido);
    }
}




