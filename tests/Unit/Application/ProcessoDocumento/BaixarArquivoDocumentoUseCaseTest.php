<?php

namespace Tests\Unit\Application\ProcessoDocumento;

use Tests\TestCase;
use App\Application\ProcessoDocumento\UseCases\BaixarArquivoDocumentoUseCase;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;

class BaixarArquivoDocumentoUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private BaixarArquivoDocumentoUseCase $useCase;
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
        
        $this->useCase = new BaixarArquivoDocumentoUseCase(
            $this->processoRepositoryMock,
            $this->processoDocumentoRepositoryMock
        );
    }

    public function test_deve_retornar_info_do_arquivo_quando_existe(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        $processoDocumentoId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $processoDocumento = new ProcessoDocumento();
        $processoDocumento->id = $processoDocumentoId;
        $processoDocumento->processo_id = $processoId;
        $processoDocumento->empresa_id = $empresaId;
        $processoDocumento->caminho_arquivo = 'processos/1/documentos/certidao.pdf';
        $processoDocumento->nome_arquivo = 'certidao.pdf';
        $processoDocumento->mime = 'application/pdf';
        
        Storage::disk('public')->put($processoDocumento->caminho_arquivo, 'conteudo do arquivo');
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoDocumentoId)
            ->once()
            ->andReturn($processoDocumento);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $processoDocumentoId);
        
        // Assert
        $this->assertNotNull($resultado);
        $this->assertIsArray($resultado);
        $this->assertEquals('processos/1/documentos/certidao.pdf', $resultado['path']);
        $this->assertEquals('certidao.pdf', $resultado['nome']);
        $this->assertEquals('application/pdf', $resultado['mime']);
    }

    public function test_deve_retornar_null_quando_arquivo_nao_existe(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        $processoDocumentoId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $processoDocumento = new ProcessoDocumento();
        $processoDocumento->id = $processoDocumentoId;
        $processoDocumento->processo_id = $processoId;
        $processoDocumento->empresa_id = $empresaId;
        $processoDocumento->caminho_arquivo = null;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoDocumentoId)
            ->once()
            ->andReturn($processoDocumento);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $processoDocumentoId);
        
        // Assert
        $this->assertNull($resultado);
    }

    public function test_deve_lancar_excecao_quando_processo_nao_existe(): void
    {
        // Arrange
        $processoId = 999;
        $empresaId = 1;
        $processoDocumentoId = 1;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Processo não encontrado ou não pertence à empresa.');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId);
    }

    public function test_deve_lancar_excecao_quando_documento_nao_existe(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        $processoDocumentoId = 999;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoDocumentoId)
            ->once()
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Documento não encontrado ou não pertence ao processo.');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

