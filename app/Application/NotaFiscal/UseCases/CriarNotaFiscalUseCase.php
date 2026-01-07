<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
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
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function executar(CriarNotaFiscalDTO $dto): NotaFiscal
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Buscar processo para obter empresa_id e validar regras de negócio
        if (!$dto->processoId) {
            throw new DomainException('Processo é obrigatório para criar nota fiscal.');
        }
        
        $processo = $this->processoRepository->buscarPorId($dto->processoId);
        if (!$processo) {
            throw new DomainException('Processo não encontrado.');
        }
        
        // Validar que o processo está em execução (regra de negócio)
        if (!$processo->estaEmExecucao()) {
            throw new DomainException('Notas fiscais só podem ser criadas para processos em execução.');
        }
        
        // Validar que há pelo menos um vínculo (empenho, contrato ou autorização)
        if (!$dto->empenhoId && !$dto->contratoId && !$dto->autorizacaoFornecimentoId) {
            throw new DomainException('Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.');
        }
        
        // Calcular custo total antes de criar a entidade
        $custoTotal = round($dto->custoProduto + $dto->custoFrete, 2);

        // Usar empresaId do processo (não do DTO)
        $notaFiscal = new NotaFiscal(
            id: null,
            empresaId: $processo->empresaId,
            processoId: $dto->processoId,
            processoItemId: $dto->processoItemId,
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

