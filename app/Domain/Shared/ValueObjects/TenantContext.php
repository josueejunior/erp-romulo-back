<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Exceptions\DomainException;

/**
 * Value Object: TenantContext
 * Representa o contexto do tenant de forma explícita
 * Remove dependência de request() dentro do domínio
 * 
 * Mantém compatibilidade com código existente que usa create() e fromCurrent()
 * e adiciona métodos estáticos get/set para uso como contexto global
 * 
 * Nota: Não pode ser readonly porque precisa de propriedade estática mutável
 */
class TenantContext
{
    private static ?TenantContext $current = null;

    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $empresaId = null,
    ) {
        if ($this->tenantId <= 0) {
            throw new DomainException('Tenant ID deve ser maior que zero.');
        }

        if ($this->empresaId !== null && $this->empresaId <= 0) {
            throw new DomainException('Empresa ID deve ser maior que zero.');
        }
    }

    /**
     * Setar o contexto atual (chamado pelo middleware)
     */
    public static function set(int $tenantId, ?int $empresaId = null): void
    {
        self::$current = new self($tenantId, $empresaId);
    }

    /**
     * Obter o contexto atual (chamado pelo Application Service)
     * 
     * @throws DomainException Se nenhum contexto foi setado
     */
    public static function get(): self
    {
        if (self::$current === null) {
            throw new DomainException('Tenant não foi inicializado. Verifique se o middleware está configurado.');
        }

        return self::$current;
    }

    /**
     * Limpar o contexto (útil para testes)
     */
    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * Verificar se há contexto setado
     */
    public static function has(): bool
    {
        return self::$current !== null;
    }

    /**
     * Criar contexto a partir do tenant atual (compatibilidade)
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
     * Criar contexto explícito (compatibilidade)
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


