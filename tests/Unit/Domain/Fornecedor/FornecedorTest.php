<?php

namespace Tests\Unit\Domain\Fornecedor;

use Tests\TestCase;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Shared\ValueObjects\Cnpj;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Exceptions\BusinessRuleException;

class FornecedorTest extends TestCase
{
    public function test_deve_criar_fornecedor_com_dados_validos(): void
    {
        // Arrange & Act
        $fornecedor = new Fornecedor(
            id: null,
            razaoSocial: 'Fornecedor Teste LTDA',
            nomeFantasia: 'Fornecedor Teste',
            cnpj: new Cnpj('12345678000190'),
            cep: '12345678',
            logradouro: 'Rua Teste',
            numero: '123',
            bairro: 'Centro',
            cidade: 'São Paulo',
            estado: 'SP',
            email: new Email('teste@fornecedor.com'),
            telefone: '11999999999',
            emails: [],
            telefones: [],
            contato: null,
            observacoes: null,
            isTransportadora: false,
        );
        
        // Assert
        $this->assertEquals('Fornecedor Teste LTDA', $fornecedor->razaoSocial);
        $this->assertEquals('Fornecedor Teste', $fornecedor->nomeFantasia);
        $this->assertInstanceOf(Cnpj::class, $fornecedor->cnpj);
        $this->assertFalse($fornecedor->isTransportadora);
    }
    
    public function test_deve_validar_cnpj_obrigatorio_para_fornecedor(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\TypeError::class);
        
        new Fornecedor(
            id: null,
            razaoSocial: 'Fornecedor Teste',
            nomeFantasia: null,
            cnpj: null, // CNPJ obrigatório
            cep: null,
            logradouro: null,
            numero: null,
            bairro: null,
            cidade: null,
            estado: null,
            email: null,
            telefone: null,
            emails: [],
            telefones: [],
            contato: null,
            observacoes: null,
            isTransportadora: false,
        );
    }
}

