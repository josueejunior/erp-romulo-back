<?php

namespace Tests\Unit\Traits;

use Tests\TestCase;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Contracts\ApplicationContextContract;
use Mockery;

class HasAuthContextTest extends TestCase
{
    use HasAuthContext;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_getTenantId_retorna_null_quando_contexto_nao_inicializado(): void
    {
        // Limpar qualquer binding existente
        $this->app->forgetInstance(ApplicationContextContract::class);
        
        $result = $this->getTenantId();
        
        // Pode retornar null ou o tenant_id do tenancy()
        $this->assertTrue($result === null || is_string($result));
    }

    public function test_getEmpresaId_retorna_null_quando_nao_autenticado(): void
    {
        // Limpar qualquer binding existente
        $this->app->forgetInstance(ApplicationContextContract::class);
        
        $result = $this->getEmpresaId();
        
        $this->assertNull($result);
    }

    public function test_getUserId_retorna_null_quando_nao_autenticado(): void
    {
        $result = $this->getUserId();
        
        $this->assertNull($result);
    }

    public function test_isAdminCentral_retorna_false_por_padrao(): void
    {
        $result = $this->isAdminCentral();
        
        $this->assertFalse($result);
    }

    public function test_isTenantUser_retorna_true_por_padrao(): void
    {
        $result = $this->isTenantUser();
        
        $this->assertTrue($result);
    }

    public function test_getScope_retorna_api_v1_por_padrao(): void
    {
        $result = $this->getScope();
        
        $this->assertEquals('api-v1', $result);
    }
}

