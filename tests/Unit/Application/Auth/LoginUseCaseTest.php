<?php

namespace Tests\Unit\Application\Auth;

use Tests\TestCase;
use App\Application\Auth\DTOs\LoginDTO;

class LoginUseCaseTest extends TestCase
{
    public function test_dto_deve_armazenar_email_corretamente(): void
    {
        // Arrange & Act
        $dto = new LoginDTO('teste@email.com', 'senha123');
        
        // Assert
        $this->assertEquals('teste@email.com', $dto->email);
    }

    public function test_dto_deve_armazenar_password_corretamente(): void
    {
        // Arrange & Act
        $dto = new LoginDTO('teste@email.com', 'senha123');
        
        // Assert
        $this->assertEquals('senha123', $dto->password);
    }

    public function test_dto_deve_permitir_tenant_id_opcional(): void
    {
        // Arrange & Act
        $dto = new LoginDTO('teste@email.com', 'senha123', '1');
        
        // Assert
        $this->assertEquals('1', $dto->tenantId);
    }

    public function test_dto_deve_ter_tenant_id_null_por_padrao(): void
    {
        // Arrange & Act
        $dto = new LoginDTO('teste@email.com', 'senha123');
        
        // Assert
        $this->assertNull($dto->tenantId);
    }
}

