<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    /**
     * Testa que endpoint de dashboard requer autenticação
     */
    public function test_dashboard_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    /**
     * Testa que endpoint de calendário requer autenticação
     */
    public function test_calendario_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/calendario/disputas');

        $response->assertStatus(401);
    }

    /**
     * Testa que endpoint de relatórios requer autenticação
     */
    public function test_relatorios_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/relatorios/financeiro');

        $response->assertStatus(401);
    }
}

