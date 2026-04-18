<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class FornecedorTest extends TestCase
{
    /**
     * Testa que endpoint de fornecedores requer autenticação
     */
    public function test_lista_fornecedores_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/fornecedores');

        $response->assertStatus(401);
    }

    /**
     * Testa que criar fornecedor requer autenticação
     */
    public function test_criar_fornecedor_requer_autenticacao(): void
    {
        $response = $this->postJson('/api/v1/fornecedores', [
            'razao_social' => 'Fornecedor Teste LTDA',
            'cnpj' => '12345678000190',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Testa que buscar fornecedor específico requer autenticação
     */
    public function test_buscar_fornecedor_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/fornecedores/1');

        $response->assertStatus(401);
    }

    /**
     * Testa que atualizar fornecedor requer autenticação
     */
    public function test_atualizar_fornecedor_requer_autenticacao(): void
    {
        $response = $this->putJson('/api/v1/fornecedores/1', [
            'razao_social' => 'Fornecedor Atualizado',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Testa que deletar fornecedor requer autenticação
     */
    public function test_deletar_fornecedor_requer_autenticacao(): void
    {
        $response = $this->deleteJson('/api/v1/fornecedores/1');

        $response->assertStatus(401);
    }
}

