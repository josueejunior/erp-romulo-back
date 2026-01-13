<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Evento disparado quando um pagamento é recusado
 * 
 * Usado para notificar o usuário e solicitar atualização do método de pagamento
 */
final class PagamentoRecusado implements DomainEvent
{
    public function __construct(
        public readonly int $assinaturaId,
        public readonly int $tenantId,
        public readonly ?int $empresaId,
        public readonly string $motivo,
        public readonly string $errorMessage,
        public readonly int $tentativasCobranca,
        public readonly \DateTimeInterface $ocorridoEm,
    ) {
    }

    public function occurredOn(): \DateTimeInterface
    {
        return $this->ocorridoEm;
    }
}


