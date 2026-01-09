<?php

declare(strict_types=1);

namespace App\Application\Onboarding\DTOs;

/**
 * DTO para iniciar onboarding
 */
class IniciarOnboardingDTO
{
    public function __construct(
        public readonly ?int $tenantId = null,
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $email = null,
    ) {
        // Validar que pelo menos um identificador foi fornecido
        if (!$tenantId && !$userId && !$sessionId && !$email) {
            throw new \InvalidArgumentException('É necessário fornecer pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
        }
    }

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(array $requestData, ?int $tenantId = null, ?int $userId = null, ?string $email = null): self
    {
        return new self(
            tenantId: $tenantId ?? (isset($requestData['tenant_id']) ? (int) $requestData['tenant_id'] : null),
            userId: $userId ?? (isset($requestData['user_id']) ? (int) $requestData['user_id'] : null),
            sessionId: $requestData['session_id'] ?? null,
            email: $email ?? ($requestData['email'] ?? null),
        );
    }
}

