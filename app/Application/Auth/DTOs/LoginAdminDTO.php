<?php

declare(strict_types=1);

namespace App\Application\Auth\DTOs;

use App\Domain\Shared\ValueObjects\Email;

/**
 * DTO para Login de Admin
 * 
 * 🔥 DDD: DTO apenas transporta dados, não tem lógica de negócio
 */
final class LoginAdminDTO
{
    public function __construct(
        public readonly Email $email,
        public readonly string $password,
    ) {}

    /**
     * Criar DTO a partir de request
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            email: Email::criar($data['email']),
            password: $data['password'],
        );
    }
}

