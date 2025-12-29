<?php

namespace Tests\Unit\Application\Fornecedor;

use Tests\TestCase;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CriarFornecedorUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CriarFornecedorUseCase $useCase;
    private $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock do repository
        $this->repositoryMock = Mockery::mock(FornecedorRepositoryInterface::class);
        $this->app->instance(FornecedorRepositoryInterface::class, $this->repositoryMock);
        
        $this->useCase = app(CriarFornecedorUseCase::class);
    }

    public function test_deve_criar_fornecedor_com_dados_validos(): void
    {
        // Arrange
        $dto = new CriarFornecedorDTO(
            razaoSocial: 'Fornecedor Teste LTDA',
            cnpj: '12345678000190',
            nomeFantasia: 'Fornecedor Teste',
            empresaId: 1,
        );
        
        $context = TenantContext::create(1);
        
        $fornecedorEsperado = \App\Domain\Factories\FornecedorFactory::criar([
            'razao_social' => 'Fornecedor Teste LTDA',
            'cnpj' => '12345678000190',
            'nome_fantasia' => 'Fornecedor Teste',
        ]);
        
        $this->repositoryMock
            ->shouldReceive('criar')
            ->once()
            ->andReturn($fornecedorEsperado);
        
        // Act
        $resultado = $this->useCase->executar($dto, $context);
        
        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals('Fornecedor Teste LTDA', $resultado->razaoSocial);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

