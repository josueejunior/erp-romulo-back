<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ApplicationContext;
use App\Contracts\ApplicationContextContract;
use Illuminate\Http\Request;
use Mockery;

class ApplicationContextTest extends TestCase
{
    private ApplicationContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new ApplicationContext();
    }

    public function test_deve_iniciar_nao_inicializado(): void
    {
        $this->assertFalse($this->context->isInitialized());
    }

    public function test_deve_retornar_null_para_tenant_id_quando_nao_inicializado(): void
    {
        $this->assertNull($this->context->getTenantIdOrNull());
    }

    public function test_deve_retornar_null_para_empresa_id_quando_nao_inicializado(): void
    {
        $this->assertNull($this->context->getEmpresaIdOrNull());
    }

    public function test_deve_lancar_excecao_ao_acessar_tenant_sem_inicializar(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ApplicationContext não foi inicializado');
        
        $this->context->tenant();
    }

    public function test_deve_lancar_excecao_ao_acessar_empresa_sem_inicializar(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ApplicationContext não foi inicializado');
        
        $this->context->empresa();
    }

    public function test_implementa_interface_correta(): void
    {
        $this->assertInstanceOf(ApplicationContextContract::class, $this->context);
    }
}

