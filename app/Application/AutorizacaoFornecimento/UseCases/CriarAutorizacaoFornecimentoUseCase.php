<?php

namespace App\Application\AutorizacaoFornecimento\UseCases;

use App\Application\AutorizacaoFornecimento\DTOs\CriarAutorizacaoFornecimentoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\AutorizacaoFornecimento\Entities\AutorizacaoFornecimento;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Autorização de Fornecimento
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarAutorizacaoFornecimentoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoRepository,
    ) {}

    public function executar(CriarAutorizacaoFornecimentoDTO $dto): AutorizacaoFornecimento
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $autorizacao = new AutorizacaoFornecimento(
            id: null,
            empresaId: $empresaId,
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
            // Converter enums para string (Domain Entity não conhece enums)
            situacao: $dto->situacao?->value,
            situacaoDetalhada: $dto->situacaoDetalhada?->value,
            vigente: $dto->vigente,
            observacoes: $dto->observacoes,
            numeroCte: $dto->numeroCte,
        );

        return $this->autorizacaoRepository->criar($autorizacao);
    }
}




