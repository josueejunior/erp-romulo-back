<?php

namespace App\Domain\Guards;

use RuntimeException;

/**
 * Guard para validação de contexto de tenant
 * 
 * Responsabilidade única: garantir que o contexto de tenant está válido.
 * Deve ser chamado nos Application Services, ANTES de usar Repositories.
 * 
 * @example
 * // No Application Service:
 * TenantContextGuard::ensureInitialized();
 * $assinatura = $this->repository->buscarAssinaturaAtual($tenantId);
 */
final class TenantContextGuard
{
    /**
     * Garante que o tenancy está inicializado
     * 
     * @throws RuntimeException Se tenancy não estiver inicializado
     */
    public static function ensureInitialized(): void
    {
        if (!tenancy()->initialized) {
            throw new RuntimeException(
                'Tenancy não inicializado. O contexto deve ser inicializado pelo middleware antes de acessar recursos do tenant.'
            );
        }
    }

    /**
     * Garante que o tenant correto está ativo
     * 
     * @param int|string $expectedTenantId ID esperado do tenant
     * @throws RuntimeException Se tenant não corresponder
     */
    public static function ensureTenant(int|string $expectedTenantId): void
    {
        self::ensureInitialized();

        $currentTenantId = tenancy()->tenant?->id;

        if ($currentTenantId === null) {
            throw new RuntimeException(
                'Nenhum tenant ativo. Esperado: ' . $expectedTenantId
            );
        }

        // Comparação flexível (string ou int)
        if ((string) $currentTenantId !== (string) $expectedTenantId) {
            throw new RuntimeException(
                "Tenant incorreto. Atual: {$currentTenantId}, Esperado: {$expectedTenantId}"
            );
        }
    }

    /**
     * Verifica se tenancy está inicializado (sem lançar exceção)
     */
    public static function isInitialized(): bool
    {
        return tenancy()->initialized;
    }

    /**
     * Retorna o ID do tenant atual ou null
     */
    public static function getCurrentTenantId(): ?string
    {
        return tenancy()->tenant?->id;
    }

    /**
     * Garante contexto para criação inicial (cadastro público)
     * Permite operação se tenantId está explícito OU tenancy inicializado
     * 
     * @param int|string|null $explicitTenantId Tenant ID fornecido explicitamente
     * @throws RuntimeException Se não houver contexto válido
     */
    public static function ensureContextForCreation(?int $explicitTenantId = null): void
    {
        $tenancyInitialized = tenancy()->initialized;
        $hasExplicitTenant = $explicitTenantId !== null;

        if (!$tenancyInitialized && !$hasExplicitTenant) {
            throw new RuntimeException(
                'Contexto inválido para criação. Tenancy deve estar inicializado ou tenantId deve ser fornecido explicitamente.'
            );
        }
    }
}

