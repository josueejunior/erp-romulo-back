<?php

namespace App\Domain\Processo\Entities;

use App\Domain\Exceptions\DomainException;
use App\Rules\NumeroProcessoRule;
use Carbon\Carbon;

/**
 * Entidade Processo - Representa um processo licitatório no domínio
 * Contém apenas regras de negócio, sem dependências de infraestrutura
 */
class Processo
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $orgaoId,
        public readonly ?int $setorId,
        public readonly ?string $modalidade,
        public readonly ?string $numeroModalidade,
        public readonly ?string $numeroProcessoAdministrativo,
        public readonly ?string $linkEdital,
        public readonly ?string $portal,
        public readonly ?string $numeroEdital,
        public readonly bool $srp = false,
        public readonly ?string $objetoResumido = null,
        public readonly ?Carbon $dataHoraSessaoPublica = null,
        public readonly ?Carbon $horarioSessaoPublica = null,
        public readonly ?string $enderecoEntrega = null,
        public readonly ?string $localEntregaDetalhado = null,
        public readonly ?string $formaEntrega = null,
        public readonly ?string $prazoEntrega = null,
        public readonly ?string $formaPrazoEntrega = null,
        public readonly ?string $prazosDetalhados = null,
        public readonly ?string $prazoPagamento = null,
        public readonly ?string $validadeProposta = null,
        public readonly ?Carbon $validadePropostaInicio = null,
        public readonly ?Carbon $validadePropostaFim = null,
        public readonly ?string $tipoSelecaoFornecedor = null,
        public readonly ?string $tipoDisputa = null,
        public readonly string $status = 'rascunho',
        public readonly ?string $statusParticipacao = null,
        public readonly ?Carbon $dataRecebimentoPagamento = null,
        public readonly ?string $observacoes = null,
        public readonly ?Carbon $dataArquivamento = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade Processo
     */
    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }
        
        // Validar formato do número da modalidade (XXXXX/YYYY)
        if ($this->numeroModalidade && !NumeroProcessoRule::isValid($this->numeroModalidade)) {
            throw new DomainException('O número da modalidade deve estar no formato XXXXX/YYYY (ex: 00123/2025).');
        }

        // Manter compatibilidade com nomenclaturas usadas nas camadas de aplicação/infra
        $statusValidos = [
            'rascunho',
            'publicado',
            'participacao',
            'em_disputa',
            'julgamento',
            'julgamento_habilitacao',
            'execucao',
            'vencido',
            'perdido',
            'pagamento',
            'encerramento',
            'arquivado',
        ];
        if (!in_array($this->status, $statusValidos)) {
            throw new DomainException('Status inválido. Status válidos: ' . implode(', ', $statusValidos));
        }

        if ($this->validadePropostaInicio && $this->validadePropostaFim) {
            if ($this->validadePropostaInicio->isAfter($this->validadePropostaFim)) {
                throw new DomainException('A data de início da validade da proposta deve ser anterior à data de fim.');
            }
        }
    }

    /**
     * Regra de negócio: Verificar se processo está em execução
     */
    public function estaEmExecucao(): bool
    {
        return in_array($this->status, ['execucao', 'vencido', 'pagamento', 'encerramento']);
    }

    /**
     * Regra de negócio: Verificar se processo pode ser editado
     */
    public function podeEditar(): bool
    {
        return !$this->estaEmExecucao() && $this->status !== 'arquivado' && $this->status !== 'perdido';
    }

    /**
     * Regra de negócio: Verificar se processo pode ser movido para julgamento
     */
    public function podeMoverParaJulgamento(): bool
    {
        return in_array($this->status, ['participacao', 'em_disputa', 'publicado']);
    }

    /**
     * Regra de negócio: Mover processo para status de julgamento
     */
    public function moverParaJulgamento(): self
    {
        if (!$this->podeMoverParaJulgamento()) {
            throw new DomainException('Processo não pode ser movido para julgamento no status atual.');
        }

        return new self(
            id: $this->id,
            empresaId: $this->empresaId,
            orgaoId: $this->orgaoId,
            setorId: $this->setorId,
            modalidade: $this->modalidade,
            numeroModalidade: $this->numeroModalidade,
            numeroProcessoAdministrativo: $this->numeroProcessoAdministrativo,
            linkEdital: $this->linkEdital,
            portal: $this->portal,
            numeroEdital: $this->numeroEdital,
            srp: $this->srp,
            objetoResumido: $this->objetoResumido,
            dataHoraSessaoPublica: $this->dataHoraSessaoPublica,
            horarioSessaoPublica: $this->horarioSessaoPublica,
            enderecoEntrega: $this->enderecoEntrega,
            localEntregaDetalhado: $this->localEntregaDetalhado,
            formaEntrega: $this->formaEntrega,
            prazoEntrega: $this->prazoEntrega,
            formaPrazoEntrega: $this->formaPrazoEntrega,
            prazosDetalhados: $this->prazosDetalhados,
            prazoPagamento: $this->prazoPagamento,
            validadeProposta: $this->validadeProposta,
            validadePropostaInicio: $this->validadePropostaInicio,
            validadePropostaFim: $this->validadePropostaFim,
            tipoSelecaoFornecedor: $this->tipoSelecaoFornecedor,
            tipoDisputa: $this->tipoDisputa,
            status: 'julgamento_habilitacao',
            statusParticipacao: $this->statusParticipacao,
            dataRecebimentoPagamento: $this->dataRecebimentoPagamento,
            observacoes: $this->observacoes,
            dataArquivamento: $this->dataArquivamento,
        );
    }

    /**
     * Regra de negócio: Arquivar processo
     */
    public function arquivar(): self
    {
        return new self(
            id: $this->id,
            empresaId: $this->empresaId,
            orgaoId: $this->orgaoId,
            setorId: $this->setorId,
            modalidade: $this->modalidade,
            numeroModalidade: $this->numeroModalidade,
            numeroProcessoAdministrativo: $this->numeroProcessoAdministrativo,
            linkEdital: $this->linkEdital,
            portal: $this->portal,
            numeroEdital: $this->numeroEdital,
            srp: $this->srp,
            objetoResumido: $this->objetoResumido,
            dataHoraSessaoPublica: $this->dataHoraSessaoPublica,
            horarioSessaoPublica: $this->horarioSessaoPublica,
            enderecoEntrega: $this->enderecoEntrega,
            localEntregaDetalhado: $this->localEntregaDetalhado,
            formaEntrega: $this->formaEntrega,
            prazoEntrega: $this->prazoEntrega,
            formaPrazoEntrega: $this->formaPrazoEntrega,
            prazosDetalhados: $this->prazosDetalhados,
            prazoPagamento: $this->prazoPagamento,
            validadeProposta: $this->validadeProposta,
            validadePropostaInicio: $this->validadePropostaInicio,
            validadePropostaFim: $this->validadePropostaFim,
            tipoSelecaoFornecedor: $this->tipoSelecaoFornecedor,
            tipoDisputa: $this->tipoDisputa,
            status: 'arquivado',
            statusParticipacao: $this->statusParticipacao,
            dataRecebimentoPagamento: $this->dataRecebimentoPagamento,
            observacoes: $this->observacoes,
            dataArquivamento: Carbon::now(),
        );
    }
}



