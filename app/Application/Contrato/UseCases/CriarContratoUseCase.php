<?php

namespace App\Application\Contrato\UseCases;

use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Contrato\Entities\Contrato;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use DomainException;

/**
 * Application Service: CriarContratoUseCase
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarContratoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function executar(CriarContratoDTO $dto): Contrato
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $contrato = new Contrato(
            id: null,
            empresaId: $empresaId,
            processoId: $dto->processoId,
            numero: $dto->numero,
            dataInicio: $dto->dataInicio,
            dataFim: $dto->dataFim,
            dataAssinatura: $dto->dataAssinatura,
            valorTotal: $dto->valorTotal,
            saldo: $dto->valorTotal, // Saldo inicial = valor total
            valorEmpenhado: 0.0,
            condicoesComerciais: $dto->condicoesComerciais,
            condicoesTecnicas: $dto->condicoesTecnicas,
            locaisEntrega: $dto->locaisEntrega,
            prazosContrato: $dto->prazosContrato,
            regrasContrato: $dto->regrasContrato,
            situacao: $dto->situacao,
            vigente: $dto->vigente,
            observacoes: $dto->observacoes,
            arquivoContrato: $dto->arquivoContrato,
            numeroCte: $dto->numeroCte,
        );

        return $this->contratoRepository->criar($contrato);
    }
}


