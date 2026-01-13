<?php

namespace App\Application\Processo\UseCases;

use App\Application\Processo\DTOs\AtualizarProcessoDTO;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Atualizar Processo
 */
class AtualizarProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(AtualizarProcessoDTO $dto): Processo
    {
        // Buscar processo existente
        $processoExistente = $this->processoRepository->buscarPorId($dto->processoId);
        
        if (!$processoExistente) {
            throw new NotFoundException('Processo', $dto->processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processoExistente->empresaId !== $dto->empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Criar nova instância com valores atualizados (imutabilidade)
        $processoAtualizado = new Processo(
            id: $dto->processoId,
            empresaId: $dto->empresaId,
            orgaoId: $dto->orgaoId ?? $processoExistente->orgaoId,
            setorId: $dto->setorId ?? $processoExistente->setorId,
            modalidade: $dto->modalidade ?? $processoExistente->modalidade,
            numeroModalidade: $dto->numeroModalidade ?? $processoExistente->numeroModalidade,
            numeroProcessoAdministrativo: $dto->numeroProcessoAdministrativo ?? $processoExistente->numeroProcessoAdministrativo,
            linkEdital: $dto->linkEdital ?? $processoExistente->linkEdital,
            portal: $dto->portal ?? $processoExistente->portal,
            numeroEdital: $dto->numeroEdital ?? $processoExistente->numeroEdital,
            srp: $dto->srp ?? $processoExistente->srp,
            objetoResumido: $dto->objetoResumido ?? $processoExistente->objetoResumido,
            dataHoraSessaoPublica: $dto->dataHoraSessaoPublica ?? $processoExistente->dataHoraSessaoPublica,
            horarioSessaoPublica: $dto->horarioSessaoPublica ?? $processoExistente->horarioSessaoPublica,
            enderecoEntrega: $dto->enderecoEntrega ?? $processoExistente->enderecoEntrega,
            localEntregaDetalhado: $dto->localEntregaDetalhado ?? $processoExistente->localEntregaDetalhado,
            formaEntrega: $dto->formaEntrega ?? $processoExistente->formaEntrega,
            prazoEntrega: $dto->prazoEntrega ?? $processoExistente->prazoEntrega,
            formaPrazoEntrega: $dto->formaPrazoEntrega ?? $processoExistente->formaPrazoEntrega,
            prazosDetalhados: $dto->prazosDetalhados ?? $processoExistente->prazosDetalhados,
            prazoPagamento: $dto->prazoPagamento ?? $processoExistente->prazoPagamento,
            validadeProposta: $dto->validadeProposta ?? $processoExistente->validadeProposta,
            validadePropostaInicio: $dto->validadePropostaInicio ?? $processoExistente->validadePropostaInicio,
            validadePropostaFim: $dto->validadePropostaFim ?? $processoExistente->validadePropostaFim,
            tipoSelecaoFornecedor: $dto->tipoSelecaoFornecedor ?? $processoExistente->tipoSelecaoFornecedor,
            tipoDisputa: $dto->tipoDisputa ?? $processoExistente->tipoDisputa,
            status: $dto->status ?? $processoExistente->status,
            statusParticipacao: $dto->statusParticipacao ?? $processoExistente->statusParticipacao,
            dataRecebimentoPagamento: $dto->dataRecebimentoPagamento ?? $processoExistente->dataRecebimentoPagamento,
            observacoes: $dto->observacoes ?? $processoExistente->observacoes,
            dataArquivamento: $processoExistente->dataArquivamento, // Não pode ser atualizado via update normal
        );
        
        // Persistir alterações
        return $this->processoRepository->atualizar($processoAtualizado);
    }
}








