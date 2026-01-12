<?php

declare(strict_types=1);

namespace App\Application\Onboarding\DTOs;

/**
 * DTO para marcar etapa de onboarding como concluída
 */
class MarcarEtapaDTO
{
    public function __construct(
        public readonly string $etapa,
        public readonly ?int $onboardingId = null,
        public readonly ?int $tenantId = null,
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $email = null,
    ) {
        if (empty($etapa)) {
            throw new \InvalidArgumentException('A etapa é obrigatória.');
        }

        // Se não tem onboardingId, precisa de pelo menos um identificador
        if (!$onboardingId && !$tenantId && !$userId && !$sessionId && !$email) {
            throw new \InvalidArgumentException('É necessário fornecer onboarding_id ou pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
        }
    }

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(array $requestData, ?int $tenantId = null, ?int $userId = null, ?string $email = null): self
    {
        return new self(
            etapa: $requestData['etapa'],
            onboardingId: isset($requestData['onboarding_id']) ? (int) $requestData['onboarding_id'] : null,
            tenantId: $tenantId ?? (isset($requestData['tenant_id']) ? (int) $requestData['tenant_id'] : null),
            userId: $userId ?? (isset($requestData['user_id']) ? (int) $requestData['user_id'] : null),
            sessionId: $requestData['session_id'] ?? null,
            email: $email ?? ($requestData['email'] ?? null),
        );
    }
}


