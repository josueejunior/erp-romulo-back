<?php

namespace App\Application\Orcamento\UseCases;

use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use DomainException;

/**
 * Application Service: BuscarOrcamentoUseCase
 * 
 * Orquestra a busca de orçamento seguindo as regras de negócio
 */
class BuscarOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {}

    public function executar(int $orcamentoId, int $empresaId, ?int $processoId = null, ?int $itemId = null): Orcamento
    {
        // Buscar orçamento
        $orcamento = $this->orcamentoRepository->buscarPorId($orcamentoId);
        
        if (!$orcamento) {
            throw new DomainException('Orçamento não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($orcamento->empresaId !== $empresaId) {
            throw new DomainException('Orçamento não pertence à empresa ativa.');
        }

        // Validar processo se fornecido
        if ($processoId && $orcamento->processoId !== $processoId) {
            throw new DomainException('Orçamento não pertence ao processo.');
        }

        // Validar item se fornecido
        if ($itemId && $orcamento->processoItemId !== $itemId) {
            throw new DomainException('Orçamento não pertence ao item.');
        }

        return $orcamento;
    }
}






