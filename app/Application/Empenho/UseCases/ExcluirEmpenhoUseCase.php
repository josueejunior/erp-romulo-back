<?php

namespace App\Application\Empenho\UseCases;

use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use DomainException;

/**
 * Application Service: ExcluirEmpenhoUseCase
 * 
 * Orquestra a exclusão de empenho seguindo as regras de negócio
 */
class ExcluirEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(int $empenhoId, int $empresaId): void
    {
        // Buscar empenho existente
        $empenho = $this->empenhoRepository->buscarPorId($empenhoId);
        
        if (!$empenho) {
            throw new DomainException('Empenho não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($empenho->empresaId !== $empresaId) {
            throw new DomainException('Empenho não pertence à empresa ativa.');
        }

        // Regra de negócio: empenho concluído pode ser excluído? (ajustar conforme regra de negócio)
        // Por enquanto, permitimos exclusão mesmo se concluído
        
        // Regra de negócio: verificar se há notas fiscais vinculadas
        // Isso deveria ser verificado via repository ou domain service
        // Por enquanto, assumimos que o repository/constraint do banco vai impedir se necessário
        
        // Excluir (regra de domínio: apenas se pertencer à empresa)
        $this->empenhoRepository->deletar($empenhoId);
    }
}






