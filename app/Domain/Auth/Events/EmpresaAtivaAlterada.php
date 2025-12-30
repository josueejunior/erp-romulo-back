<?php

namespace App\Domain\Auth\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Domain Event: Empresa Ativa Alterada
 * Disparado quando um usuário troca sua empresa ativa
 */
class EmpresaAtivaAlterada implements DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly int $empresaIdAntiga,
        public readonly int $empresaIdNova,
        public readonly \DateTimeImmutable $ocorreuEm,
    ) {}

    /**
     * ID do agregado (usuário)
     */
    public function agregadoId(): string
    {
        return (string) $this->userId;
    }

    /**
     * Quando o evento ocorreu
     */
    public function ocorreuEm(): \DateTimeImmutable
    {
        return $this->ocorreuEm;
    }
}

