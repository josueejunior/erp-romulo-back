<?php

namespace App\Domain\Suporte\Entities;

use App\Domain\Exceptions\DomainException;

class Ticket
{
    /**
     * @param TicketResponse[] $responses
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $numero,
        public readonly int $userId,
        public readonly int $empresaId,
        public readonly string $descricao,
        public readonly ?string $anexoUrl = null,
        public readonly string $status = 'aberto',
        public readonly ?string $observacaoInterna = null,
        public readonly int $responsesCount = 0,
        public readonly array $responses = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new DomainException('Usuário inválido para criação de ticket.');
        }

        if ($this->empresaId <= 0) {
            throw new DomainException('Empresa inválida para criação de ticket.');
        }

        if (empty(trim($this->descricao))) {
            throw new DomainException('Descrição do ticket é obrigatória.');
        }

        if (mb_strlen($this->descricao) > 5000) {
            throw new DomainException('Descrição do ticket deve ter no máximo 5000 caracteres.');
        }
    }
}
