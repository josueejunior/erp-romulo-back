<?php

namespace Tests\Unit\Domain\Fornecedor;

use Tests\TestCase;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Factories\FornecedorFactory;
use App\Domain\Exceptions\DomainException;

class FornecedorTest extends TestCase
{
    public function test_deve_criar_fornecedor_com_dados_validos(): void
    {
        // Arrange & Act
        $fornecedor = FornecedorFactory::criarParaTeste([
            'razao_social' => 'Fornecedor Teste LTDA',
            'nome_fantasia' => 'Fornecedor Teste',
            'cnpj' => '12345678000190',
            'empresa_id' => 1,
        ]);
        
        // Assert
        $this->assertEquals('Fornecedor Teste LTDA', $fornecedor->razaoSocial);
        $this->assertEquals('Fornecedor Teste', $fornecedor->nomeFantasia);
        $this->assertEquals('12345678000190', $fornecedor->cnpj);
        $this->assertFalse($fornecedor->isTransportadora);
    }
    
    public function test_deve_validar_razao_social_obrigatoria(): void
    {
        // Arrange & Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A razão social é obrigatória.');
        
        FornecedorFactory::criar([
            'razao_social' => '',
            'empresa_id' => 1,
        ]);
    }
    
    public function test_deve_validar_empresa_obrigatoria(): void
    {
        // Arrange & Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A empresa é obrigatória.');
        
        FornecedorFactory::criar([
            'razao_social' => 'Fornecedor Teste',
            'empresa_id' => 0,
        ]);
    }

    public function test_deve_exigir_empresa_id_na_factory(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empresa_id é obrigatório');
        
        FornecedorFactory::criar([
            'razao_social' => 'Fornecedor Teste',
            // empresa_id não fornecido
        ]);
    }
}

