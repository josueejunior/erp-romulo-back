<?php

namespace App\Application\AutorizacaoFornecimento\UseCases;

use App\Application\AutorizacaoFornecimento\DTOs\CriarAutorizacaoFornecimentoDTO;
use App\Domain\AutorizacaoFornecimento\Entities\AutorizacaoFornecimento;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Autorização de Fornecimento
 */
class CriarAutorizacaoFornecimentoUseCase
{
    public function __construct(
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoRepository,
    ) {}

    public function executar(CriarAutorizacaoFornecimentoDTO $dto): AutorizacaoFornecimento
    {
        $autorizacao = new AutorizacaoFornecimento(
            id: null,
            empresaId: $dto->empresaId,
            processoId: $dto->processoId,
            contratoId: $dto->contratoId,
            numero: $dto->numero,
            data: $dto->data,
            dataAdjudicacao: $dto->dataAdjudicacao,
            dataHomologacao: $dto->dataHomologacao,
            dataFimVigencia: $dto->dataFimVigencia,
            condicoesAf: $dto->condicoesAf,
            itensArrematados: $dto->itensArrematados,
            valor: $dto->valor,
            saldo: $dto->valor, // Saldo inicial = valor
            valorEmpenhado: 0.0,
            situacao: $dto->situacao,
            situacaoDetalhada: $dto->situacaoDetalhada,
            vigente: $dto->vigente,
            observacoes: $dto->observacoes,
            numeroCte: $dto->numeroCte,
        );

        return $this->autorizacaoRepository->criar($autorizacao);
    }
}


