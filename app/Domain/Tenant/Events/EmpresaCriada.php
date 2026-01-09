<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Events;

use App\Domain\Shared\Events\DomainEvent;

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
    ) {}
}

