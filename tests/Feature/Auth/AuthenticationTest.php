<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    /**
     * Testa que login com credenciais inválidas retorna erro
     */
    public function test_login_com_credenciais_invalidas_retorna_erro(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'naoexiste@email.com',
            'password' => 'senhaerrada',
        ]);

        $response->assertStatus(401)
                 ->assertJsonStructure(['message']);
    }

    /**
     * Testa que endpoint protegido requer autenticação
     */
    public function test_endpoint_protegido_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    /**
     * Testa que OPTIONS preflight retorna headers CORS
     */
    public function test_preflight_retorna_headers_cors(): void
    {
        $response = $this->options('/api/v1/auth/login', [], [
            'Origin' => 'https://gestor.addsimp.com',
            'Access-Control-Request-Method' => 'POST',
        ]);

        $response->assertStatus(200)
                 ->assertHeader('Access-Control-Allow-Origin');
    }

    /**
     * Testa que login com campos vazios retorna validação
     */
    public function test_login_com_campos_vazios_retorna_validacao(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }
}

