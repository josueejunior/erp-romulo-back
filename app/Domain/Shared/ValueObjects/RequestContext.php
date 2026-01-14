<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

/**
 * Value Object para contexto da requisição
 * 
 * Agrupa informações da requisição HTTP (IP, User Agent, etc.)
 * Imutável e validado
 */
final readonly class RequestContext
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?int $userId = null,
        public ?int $tenantId = null,
    ) {
        $this->validate();
    }

    /**
     * Valida o value object
     */
    private function validate(): void
    {
        if ($this->ipAddress !== null && strlen($this->ipAddress) > 45) {
            throw new \InvalidArgumentException('IP address não pode exceder 45 caracteres.');
        }

        if ($this->userAgent !== null && strlen($this->userAgent) > 500) {
            throw new \InvalidArgumentException('User Agent não pode exceder 500 caracteres.');
        }
    }

    /**
     * Cria a partir da requisição atual do Laravel
     */
    public static function fromRequest(?\Illuminate\Http\Request $request = null): self
    {
        if ($request === null) {
            $request = request();
        }

        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            userId: auth()->id(),
            tenantId: tenancy()?->tenant?->id,
        );
    }

    /**
     * Cria contexto vazio (para jobs/commands)
     */
    public static function empty(): self
    {
        return new self(
            ipAddress: null,
            userAgent: null,
            userId: null,
            tenantId: null,
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
        ];
    }
}






