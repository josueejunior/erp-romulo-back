<?php

namespace Tests\Unit\Domain\Assinatura;

use Tests\TestCase;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

class AssinaturaTest extends TestCase
{
    public function test_deve_criar_assinatura_com_dados_validos(): void
    {
        // Arrange & Act
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
            dataInicio: Carbon::now(),
            dataFim: Carbon::now()->addMonth(),
            valorPago: 99.90
        );
        
        // Assert
        $this->assertEquals(1, $assinatura->id);
        $this->assertEquals(1, $assinatura->userId);
        $this->assertEquals(1, $assinatura->tenantId);
        $this->assertEquals('ativa', $assinatura->status);
        $this->assertEquals(99.90, $assinatura->valorPago);
    }

    public function test_deve_identificar_assinatura_ativa(): void
    {
        // Arrange
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
            dataInicio: Carbon::now()->subDays(10),
            dataFim: Carbon::now()->addDays(20),
        );
        
        // Act & Assert
        $this->assertTrue($assinatura->isAtiva());
    }

    public function test_deve_identificar_assinatura_expirada(): void
    {
        // Arrange - com grace period de 7 dias
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
            dataInicio: Carbon::now()->subDays(30),
            dataFim: Carbon::now()->subDays(10), // Expirou há 10 dias (além do grace period de 7)
            diasGracePeriod: 7
        );
        
        // Act & Assert
        $this->assertTrue($assinatura->isExpirada());
    }

    public function test_deve_identificar_assinatura_cancelada(): void
    {
        // Arrange
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'cancelada',
            dataInicio: Carbon::now()->subDays(10),
            dataFim: Carbon::now()->addDays(20),
        );
        
        // Act & Assert
        $this->assertFalse($assinatura->isAtiva());
    }

    public function test_deve_calcular_dias_restantes(): void
    {
        // Arrange
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
            dataInicio: Carbon::now(),
            dataFim: Carbon::now()->addDays(15),
        );
        
        // Act & Assert
        $this->assertEquals(15, $assinatura->diasRestantes());
    }

    public function test_deve_validar_user_id_obrigatorio(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O usuário é obrigatório');
        
        new Assinatura(
            id: 1,
            userId: 0, // Inválido
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
        );
    }

    public function test_deve_validar_plano_id_obrigatorio(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O plano é obrigatório');
        
        new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 0, // Inválido
            status: 'ativa',
        );
    }

    public function test_deve_validar_status_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Status de assinatura inválido');
        
        new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'invalido', // Status inválido
        );
    }

    public function test_deve_identificar_grace_period(): void
    {
        // Arrange - Assinatura expirou há 3 dias (dentro do grace period de 7)
        $assinatura = new Assinatura(
            id: 1,
            userId: 1,
            tenantId: 1,
            planoId: 1,
            status: 'ativa',
            dataInicio: Carbon::now()->subDays(30),
            dataFim: Carbon::now()->subDays(3),
            diasGracePeriod: 7
        );
        
        // Act & Assert
        $this->assertTrue($assinatura->estaNoGracePeriod());
        $this->assertFalse($assinatura->isExpirada());
    }
}

