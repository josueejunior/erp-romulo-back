<?php

namespace App\Domain\Shared\ValueObjects;

use DomainException;

/**
 * Value Object: TenantContext
 * Representa o contexto do tenant de forma explícita
 * Remove dependência de request() dentro do domínio
 */
readonly class TenantContext
{
    public function __construct(
        public int $tenantId,
        public ?int $empresaId = null,
    ) {
        if ($this->tenantId <= 0) {
            throw new DomainException('Tenant ID deve ser maior que zero.');
        }

        if ($this->empresaId !== null && $this->empresaId <= 0) {
            throw new DomainException('Empresa ID deve ser maior que zero.');
        }
    }

    /**
     * Criar contexto a partir do tenant atual
     */
    public static function fromCurrent(): self
    {
        $tenant = tenancy()->tenant;
        if (!$tenant) {
            throw new DomainException('Nenhum tenant ativo no contexto atual.');
        }

        return new self(
            tenantId: $tenant->id,
            empresaId: null, // Pode ser obtido separadamente se necessário
        );
    }

    /**
     * Criar contexto explícito
     */
    public static function create(int $tenantId, ?int $empresaId = null): self
    {
        return new self($tenantId, $empresaId);
    }

    /**
     * Criar contexto com empresa
     */
    public function comEmpresa(int $empresaId): self
    {
        return new self($this->tenantId, $empresaId);
    }
}

