<?php

namespace App\Domain\OrgaoResponsavel\Entities;

use DomainException;

/**
 * Entidade OrgaoResponsavel - Representa um responsável de um órgão
 */
class OrgaoResponsavel
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly int $orgaoId,
        public readonly string $nome,
        public readonly ?string $cargo = null,
        public readonly ?array $emails = null,
        public readonly ?array $telefones = null,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if ($this->orgaoId <= 0) {
            throw new DomainException('O órgão é obrigatório.');
        }

        if (empty(trim($this->nome))) {
            throw new DomainException('O nome do responsável é obrigatório.');
        }

        // Validar emails se fornecidos
        if ($this->emails !== null) {
            foreach ($this->emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new DomainException("O e-mail '{$email}' é inválido.");
                }
            }
        }
    }
}

