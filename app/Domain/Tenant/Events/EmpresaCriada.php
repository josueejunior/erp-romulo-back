<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: Empresa Criada
 * 
 * Disparado quando uma empresa (tenant) é criada no sistema
 */
readonly class EmpresaCriada implements DomainEvent
{
    public function __construct(
        public int $tenantId,
        public string $razaoSocial,
        public ?string $cnpj,
        public ?string $email,
        public int $empresaId,
        public DateTimeImmutable $ocorreuEm = new DateTimeImmutable()
    ) {}

    /**
     * Data/hora em que o evento ocorreu
     */
    public function ocorreuEm(): DateTimeImmutable
    {
        return $this->ocorreuEm;
    }

    /**
     * ID da agregação que gerou o evento (tenant_id)
     */
    public function agregadoId(): string
    {
        return (string) $this->tenantId;
    }
}

