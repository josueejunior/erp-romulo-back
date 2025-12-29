<?php

namespace App\Domain\Factories;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Shared\ValueObjects\Cnpj;
use App\Domain\Shared\ValueObjects\Email;

/**
 * Factory para criar entidades Fornecedor
 * 
 * Centraliza a lógica de criação e validação
 */
class FornecedorFactory
{
    /**
     * Criar Fornecedor a partir de array de dados
     */
    public static function criar(array $dados): Fornecedor
    {
        return new Fornecedor(
            id: null, // Novo registro
            razaoSocial: $dados['razao_social'] ?? '',
            nomeFantasia: $dados['nome_fantasia'] ?? null,
            cnpj: isset($dados['cnpj']) ? new Cnpj($dados['cnpj']) : null,
            cep: $dados['cep'] ?? null,
            logradouro: $dados['logradouro'] ?? null,
            numero: $dados['numero'] ?? null,
            bairro: $dados['bairro'] ?? null,
            complemento: $dados['complemento'] ?? null,
            cidade: $dados['cidade'] ?? null,
            estado: $dados['estado'] ?? null,
            email: isset($dados['email']) ? new Email($dados['email']) : null,
            telefone: $dados['telefone'] ?? null,
            emails: $dados['emails'] ?? [],
            telefones: $dados['telefones'] ?? [],
            contato: $dados['contato'] ?? null,
            observacoes: $dados['observacoes'] ?? null,
            isTransportadora: $dados['is_transportadora'] ?? false,
        );
    }
    
    /**
     * Criar Fornecedor para testes
     */
    public static function criarParaTeste(array $dados = []): Fornecedor
    {
        $dadosPadrao = [
            'razao_social' => 'Fornecedor Teste LTDA',
            'cnpj' => '12345678000190',
            'email' => 'teste@fornecedor.com',
            'is_transportadora' => false,
        ];
        
        return self::criar(array_merge($dadosPadrao, $dados));
    }
}

