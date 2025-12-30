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
}

