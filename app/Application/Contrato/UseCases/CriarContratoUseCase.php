<?php

namespace App\Application\Contrato\UseCases;

use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Domain\Contrato\Entities\Contrato;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Application Service: CriarContratoUseCase
 * 
 * 🔥 ONDE O TENANT É USADO DE VERDADE
 * 
 * O service pega o tenant_id do TenantContext (setado pelo middleware).
 * O controller não sabe que isso existe.
 */
class CriarContratoUseCase
{
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function executar(CriarContratoDTO $dto): Contrato
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Por enquanto, mantemos empresaId no DTO para compatibilidade
        // Mas o tenant_id já está disponível no contexto se necessário
        $contrato = new Contrato(
            id: null,
            empresaId: $dto->empresaId,
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


