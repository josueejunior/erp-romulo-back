<?php

namespace App\Application\Orcamento\UseCases;

use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use DomainException;

/**
 * Application Service: ExcluirOrcamentoUseCase
 * 
 * Orquestra a exclusão de orçamento seguindo as regras de negócio
 */
class ExcluirOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {}

    public function executar(int $orcamentoId, int $empresaId): void
    {
        // Buscar orçamento existente
        $orcamento = $this->orcamentoRepository->buscarPorId($orcamentoId);
        
        if (!$orcamento) {
            throw new DomainException('Orçamento não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($orcamento->empresaId !== $empresaId) {
            throw new DomainException('Orçamento não pertence à empresa ativa.');
        }

        // Regra de negócio: verificar se há dependências
        // Por enquanto, assumimos que o repository/constraint do banco vai impedir se necessário
        
        // Excluir (regra de domínio: apenas se pertencer à empresa)
        $this->orcamentoRepository->deletar($orcamentoId);
    }
}








