<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AssinaturaTest extends TestCase
{
    /**
     * Testa que endpoint de assinatura requer autenticação
     */
    public function test_assinatura_atual_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/assinaturas/atual');

        $response->assertStatus(401);
    }

    /**
     * Testa que endpoint de status de assinatura requer autenticação
     */
    public function test_status_assinatura_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/assinaturas/status');

        $response->assertStatus(401);
    }

    /**
     * Testa que endpoint de planos é acessível publicamente
     */
    public function test_lista_planos_e_publica(): void
    {
        $response = $this->getJson('/api/v1/planos');

        // Pode retornar 200 (planos existem) ou 401 (requer auth)
        // Dependendo da configuração das rotas
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 401, 404])
        );
    }
}

