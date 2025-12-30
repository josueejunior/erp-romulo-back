<?php

namespace App\Domain\Plano\Entities;

use App\Domain\Exceptions\DomainException;

/**
 * Entidade Plano - Representa um plano de assinatura no domínio
 */
class Plano
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $nome,
        public readonly ?string $descricao = null,
        public readonly ?float $precoMensal = null,
        public readonly ?float $precoAnual = null,
        public readonly ?int $limiteProcessos = null,
        public readonly ?int $limiteUsuarios = null,
        public readonly ?int $limiteArmazenamentoMb = null,
        public readonly ?array $recursosDisponiveis = null,
        public readonly bool $ativo = true,
        public readonly ?int $ordem = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->nome))) {
            throw new DomainException('O nome do plano é obrigatório.');
        }

        if ($this->precoMensal !== null && $this->precoMensal < 0) {
            throw new DomainException('O preço mensal não pode ser negativo.');
        }

        if ($this->precoAnual !== null && $this->precoAnual < 0) {
            throw new DomainException('O preço anual não pode ser negativo.');
        }

        if ($this->limiteProcessos !== null && $this->limiteProcessos < 0) {
            throw new DomainException('O limite de processos não pode ser negativo.');
        }

        if ($this->limiteUsuarios !== null && $this->limiteUsuarios < 0) {
            throw new DomainException('O limite de usuários não pode ser negativo.');
        }

        if ($this->limiteArmazenamentoMb !== null && $this->limiteArmazenamentoMb < 0) {
            throw new DomainException('O limite de armazenamento não pode ser negativo.');
        }
    }

    /**
     * Verifica se o plano está ativo
     */
    public function isAtivo(): bool
    {
        return $this->ativo === true;
    }

    /**
     * Verifica se o plano tem limite ilimitado para processos
     */
    public function temProcessosIlimitados(): bool
    {
        return $this->limiteProcessos === null;
    }

    /**
     * Verifica se o plano tem limite ilimitado para usuários
     */
    public function temUsuariosIlimitados(): bool
    {
        return $this->limiteUsuarios === null;
    }

    /**
     * Factory method para criar um novo plano
     */
    public static function criar(
        string $nome,
        ?string $descricao = null,
        ?float $precoMensal = null,
        ?float $precoAnual = null,
        ?int $limiteProcessos = null,
        ?int $limiteUsuarios = null,
        ?int $limiteArmazenamentoMb = null,
        ?array $recursosDisponiveis = null,
        bool $ativo = true,
        ?int $ordem = null,
    ): self {
        return new self(
            id: null,
            nome: $nome,
            descricao: $descricao,
            precoMensal: $precoMensal,
            precoAnual: $precoAnual,
            limiteProcessos: $limiteProcessos,
            limiteUsuarios: $limiteUsuarios,
            limiteArmazenamentoMb: $limiteArmazenamentoMb,
            recursosDisponiveis: $recursosDisponiveis,
            ativo: $ativo,
            ordem: $ordem,
        );
    }

    /**
     * Métodos setters para atualização (retornam nova instância imutável)
     */
    public function setNome(string $nome): self
    {
        return new self(
            id: $this->id,
            nome: $nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setDescricao(?string $descricao): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setPrecoMensal(?float $precoMensal): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setPrecoAnual(?float $precoAnual): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setLimiteProcessos(?int $limiteProcessos): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setLimiteUsuarios(?int $limiteUsuarios): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setLimiteArmazenamentoMb(?int $limiteArmazenamentoMb): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setRecursosDisponiveis(?array $recursosDisponiveis): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $this->ordem,
        );
    }

    public function setAtivo(bool $ativo): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $ativo,
            ordem: $this->ordem,
        );
    }

    public function setOrdem(?int $ordem): self
    {
        return new self(
            id: $this->id,
            nome: $this->nome,
            descricao: $this->descricao,
            precoMensal: $this->precoMensal,
            precoAnual: $this->precoAnual,
            limiteProcessos: $this->limiteProcessos,
            limiteUsuarios: $this->limiteUsuarios,
            limiteArmazenamentoMb: $this->limiteArmazenamentoMb,
            recursosDisponiveis: $this->recursosDisponiveis,
            ativo: $this->ativo,
            ordem: $ordem,
        );
    }
}

