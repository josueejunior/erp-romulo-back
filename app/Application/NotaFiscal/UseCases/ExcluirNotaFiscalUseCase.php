<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use DomainException;

/**
 * Application Service: ExcluirNotaFiscalUseCase
 * 
 * Orquestra a exclusão de nota fiscal seguindo as regras de negócio
 */
class ExcluirNotaFiscalUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    public function executar(int $notaFiscalId, int $empresaId): void
    {
        // Buscar nota fiscal existente
        $notaFiscal = $this->notaFiscalRepository->buscarPorId($notaFiscalId);
        
        if (!$notaFiscal) {
            throw new DomainException('Nota fiscal não encontrada.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($notaFiscal->empresaId !== $empresaId) {
            throw new DomainException('Nota fiscal não pertence à empresa ativa.');
        }

        // Excluir (regra de domínio: apenas se pertencer à empresa)
        $this->notaFiscalRepository->deletar($notaFiscalId);
    }
}




