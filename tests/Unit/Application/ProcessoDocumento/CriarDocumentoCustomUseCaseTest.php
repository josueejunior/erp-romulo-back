<?php

namespace Tests\Unit\Application\ProcessoDocumento;

use Tests\TestCase;
use App\Application\ProcessoDocumento\UseCases\CriarDocumentoCustomUseCase;
use App\Application\ProcessoDocumento\DTOs\CriarDocumentoCustomDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class CriarDocumentoCustomUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CriarDocumentoCustomUseCase $useCase;
    private $processoRepositoryMock;
    private $processoDocumentoRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->processoRepositoryMock = Mockery::mock(ProcessoRepositoryInterface::class);
        $this->processoDocumentoRepositoryMock = Mockery::mock(ProcessoDocumentoRepositoryInterface::class);
        
        $this->app->instance(ProcessoRepositoryInterface::class, $this->processoRepositoryMock);
        $this->app->instance(ProcessoDocumentoRepositoryInterface::class, $this->processoDocumentoRepositoryMock);
        
        $this->useCase = new CriarDocumentoCustomUseCase(
            $this->processoRepositoryMock,
            $this->processoDocumentoRepositoryMock
        );
    }

    public function test_deve_criar_documento_custom_com_sucesso(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $dto = CriarDocumentoCustomDTO::fromArray([
            'titulo_custom' => 'Certidão Específica',
            'exigido' => true,
            'disponivel_envio' => false,
            'status' => 'pendente',
        ]);
        
        $processoDocumentoEsperado = new ProcessoDocumento();
        $processoDocumentoEsperado->id = 1;
        $processoDocumentoEsperado->documento_custom = true;
        $processoDocumentoEsperado->titulo_custom = 'Certidão Específica';
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function ($dados) use ($processoId, $empresaId) {
                return $dados['empresa_id'] === $empresaId
                    && $dados['processo_id'] === $processoId
                    && $dados['documento_habilitacao_id'] === null
                    && $dados['documento_custom'] === true
                    && $dados['titulo_custom'] === 'Certidão Específica'
                    && $dados['exigido'] === true
                    && $dados['disponivel_envio'] === false
                    && $dados['status'] === 'pendente';
            }))
            ->andReturn($processoDocumentoEsperado);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $dto);
        
        // Assert
        $this->assertInstanceOf(ProcessoDocumento::class, $resultado);
        $this->assertTrue($resultado->documento_custom);
        $this->assertEquals('Certidão Específica', $resultado->titulo_custom);
    }

    public function test_deve_criar_documento_custom_com_arquivo(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $dto = CriarDocumentoCustomDTO::fromArray([
            'titulo_custom' => 'Certidão Específica',
            'status' => 'pendente',
        ]);
        
        $arquivo = UploadedFile::fake()->create('certidao.pdf', 1024);
        
        $processoDocumentoEsperado = new ProcessoDocumento();
        $processoDocumentoEsperado->id = 1;
        $processoDocumentoEsperado->status = 'anexado';
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function ($dados) {
                return isset($dados['nome_arquivo'])
                    && isset($dados['caminho_arquivo'])
                    && isset($dados['mime'])
                    && isset($dados['tamanho_bytes'])
                    && $dados['status'] === 'anexado';
            }))
            ->andReturn($processoDocumentoEsperado);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $dto, $arquivo);
        
        // Assert
        $this->assertInstanceOf(ProcessoDocumento::class, $resultado);
        $this->assertEquals('anexado', $resultado->status);
    }

    public function test_deve_validar_tamanho_do_arquivo(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $dto = CriarDocumentoCustomDTO::fromArray([
            'titulo_custom' => 'Certidão Específica',
        ]);
        
        // Arquivo muito grande (11MB)
        $arquivo = UploadedFile::fake()->create('certidao.pdf', 11 * 1024 * 1024);
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Arquivo muito grande. Tamanho máximo permitido: 10MB');
        
        $this->useCase->executar($processoId, $empresaId, $dto, $arquivo);
    }

    public function test_deve_validar_tipo_do_arquivo(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $dto = CriarDocumentoCustomDTO::fromArray([
            'titulo_custom' => 'Certidão Específica',
        ]);
        
        // Arquivo com tipo não permitido
        $arquivo = UploadedFile::fake()->create('certidao.exe', 100);
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Tipo de arquivo não permitido');
        
        $this->useCase->executar($processoId, $empresaId, $dto, $arquivo);
    }

    public function test_deve_lancar_excecao_quando_processo_nao_existe(): void
    {
        // Arrange
        $processoId = 999;
        $empresaId = 1;
        
        $dto = CriarDocumentoCustomDTO::fromArray([
            'titulo_custom' => 'Certidão Específica',
        ]);
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Processo não encontrado ou não pertence à empresa.');
        
        $this->useCase->executar($processoId, $empresaId, $dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

