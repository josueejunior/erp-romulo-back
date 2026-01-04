<?php

namespace App\Application\Processo\UseCases;

use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Criar Processo
 * 
 * Coordena o fluxo de criação, mas não sabe nada de banco de dados
 */
class CriarProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarProcessoDTO $dto): Processo
    {
        // Obter tenant_id e empresa_id do contexto
        $context = TenantContext::get();
        
        // Usa empresaId do DTO se informado, senão tenta do contexto, senão do app container
        $empresaId = $dto->empresaId > 0 
            ? $dto->empresaId 
            : ($context->empresaId ?? (app()->bound('current_empresa_id') ? app('current_empresa_id') : 0));
        
        // Criar entidade Processo (regras de negócio)
        $processo = new Processo(
            id: null, // Será gerado pelo repository
            empresaId: $empresaId,
            orgaoId: $dto->orgaoId,
            setorId: $dto->setorId,
            modalidade: $dto->modalidade,
            numeroModalidade: $dto->numeroModalidade,
            numeroProcessoAdministrativo: $dto->numeroProcessoAdministrativo,
            linkEdital: $dto->linkEdital,
            portal: $dto->portal,
            numeroEdital: $dto->numeroEdital,
            srp: $dto->srp,
            objetoResumido: $dto->objetoResumido,
            dataHoraSessaoPublica: $dto->dataHoraSessaoPublica,
            horarioSessaoPublica: $dto->horarioSessaoPublica,
            enderecoEntrega: $dto->enderecoEntrega,
            localEntregaDetalhado: $dto->localEntregaDetalhado,
            formaEntrega: $dto->formaEntrega,
            prazoEntrega: $dto->prazoEntrega,
            formaPrazoEntrega: $dto->formaPrazoEntrega,
            prazosDetalhados: $dto->prazosDetalhados,
            prazoPagamento: $dto->prazoPagamento,
            validadeProposta: $dto->validadeProposta,
            validadePropostaInicio: $dto->validadePropostaInicio,
            validadePropostaFim: $dto->validadePropostaFim,
            tipoSelecaoFornecedor: $dto->tipoSelecaoFornecedor,
            tipoDisputa: $dto->tipoDisputa,
            status: $dto->status,
            statusParticipacao: $dto->statusParticipacao,
            dataRecebimentoPagamento: $dto->dataRecebimentoPagamento,
            observacoes: $dto->observacoes,
        );

        // Persistir processo (infraestrutura)
        return $this->processoRepository->criar($processo);
    }
}



