<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Application Service: CriarNotaFiscalUseCase
 * 
 * 🔥 ONDE O TENANT É USADO DE VERDADE
 * 
 * O service pega o tenant_id do TenantContext (setado pelo middleware).
 * O controller não sabe que isso existe.
 */
class CriarNotaFiscalUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    public function executar(CriarNotaFiscalDTO $dto): NotaFiscal
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Calcular custo total antes de criar a entidade
        $custoTotal = round($dto->custoProduto + $dto->custoFrete, 2);

        // Por enquanto, mantemos empresaId no DTO para compatibilidade
        // Mas o tenant_id já está disponível no contexto se necessário
        $notaFiscal = new NotaFiscal(
            id: null,
            empresaId: $dto->empresaId,
            processoId: $dto->processoId,
            empenhoId: $dto->empenhoId,
            contratoId: $dto->contratoId,
            autorizacaoFornecimentoId: $dto->autorizacaoFornecimentoId,
            tipo: $dto->tipo,
            numero: $dto->numero,
            serie: $dto->serie,
            dataEmissao: $dto->dataEmissao,
            fornecedorId: $dto->fornecedorId,
            transportadora: $dto->transportadora,
            numeroCte: $dto->numeroCte,
            dataEntregaPrevista: $dto->dataEntregaPrevista,
            dataEntregaRealizada: $dto->dataEntregaRealizada,
            situacaoLogistica: $dto->situacaoLogistica,
            valor: $dto->valor,
            custoProduto: $dto->custoProduto,
            custoFrete: $dto->custoFrete,
            custoTotal: $custoTotal,
            comprovantePagamento: $dto->comprovantePagamento,
            arquivo: $dto->arquivo,
            situacao: $dto->situacao,
            dataPagamento: $dto->dataPagamento,
            observacoes: $dto->observacoes,
        );

        return $this->notaFiscalRepository->criar($notaFiscal);
    }
}

