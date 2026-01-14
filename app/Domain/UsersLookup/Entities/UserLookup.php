<?php

declare(strict_types=1);

namespace App\Domain\UsersLookup\Entities;

/**
 * Entidade UserLookup (Domain)
 * 
 * Representa um registro na tabela global de lookup para validação rápida
 */
final class UserLookup
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $cnpj,
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly ?int $empresaId,
        public readonly string $status,  // 'ativo', 'inativo', 'deleted'
    ) {}
    
    public function isAtivo(): bool
    {
        return $this->status === 'ativo';
    }
    
    public function isInativo(): bool
    {
        return $this->status === 'inativo';
    }
}






