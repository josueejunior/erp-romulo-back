<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ProcessoTest extends TestCase
{
    /**
     * Testa que endpoint de processos requer autenticação
     */
    public function test_lista_processos_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/processos');

        $response->assertStatus(401);
    }

    /**
     * Testa que endpoint de resumo requer autenticação
     */
    public function test_resumo_processos_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/processos/resumo');

        $response->assertStatus(401);
    }

    /**
     * Testa que criar processo requer autenticação
     */
    public function test_criar_processo_requer_autenticacao(): void
    {
        $response = $this->postJson('/api/v1/processos', [
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Teste',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Testa que buscar processo específico requer autenticação
     */
    public function test_buscar_processo_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/processos/1');

        $response->assertStatus(401);
    }
}

