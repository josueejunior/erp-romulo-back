<?php

namespace Tests\Unit\Application\ProcessoDocumento;

use Tests\TestCase;
use App\Application\ProcessoDocumento\UseCases\ImportarDocumentosProcessoUseCase;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Modules\Processo\Models\Processo;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;

class ImportarDocumentosProcessoUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ImportarDocumentosProcessoUseCase $useCase;
    private $processoRepositoryMock;
    private $processoDocumentoRepositoryMock;
    private $documentoHabilitacaoRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processoRepositoryMock = Mockery::mock(ProcessoRepositoryInterface::class);
        $this->processoDocumentoRepositoryMock = Mockery::mock(ProcessoDocumentoRepositoryInterface::class);
        $this->documentoHabilitacaoRepositoryMock = Mockery::mock(DocumentoHabilitacaoRepositoryInterface::class);
        
        $this->app->instance(ProcessoRepositoryInterface::class, $this->processoRepositoryMock);
        $this->app->instance(ProcessoDocumentoRepositoryInterface::class, $this->processoDocumentoRepositoryMock);
        $this->app->instance(DocumentoHabilitacaoRepositoryInterface::class, $this->documentoHabilitacaoRepositoryMock);
        
        $this->useCase = new ImportarDocumentosProcessoUseCase(
            $this->processoRepositoryMock,
            $this->processoDocumentoRepositoryMock,
            $this->documentoHabilitacaoRepositoryMock
        );
    }

    public function test_deve_importar_documentos_ativos_com_sucesso(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $documento1 = new DocumentoHabilitacao();
        $documento1->id = 10;
        $documento1->empresa_id = $empresaId;
        
        $documento2 = new DocumentoHabilitacao();
        $documento2->id = 11;
        $documento2->empresa_id = $empresaId;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->documentoHabilitacaoRepositoryMock
            ->shouldReceive('buscarAtivosPorEmpresa')
            ->with($empresaId)
            ->once()
            ->andReturn(collect([$documento1, $documento2]));
        
        // Primeiro documento não existe
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('existePorProcessoEDocumento')
            ->with($processoId, 10)
            ->once()
            ->andReturn(false);
        
        // Segundo documento já existe
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('existePorProcessoEDocumento')
            ->with($processoId, 11)
            ->once()
            ->andReturn(true);
        
        // Criar apenas o primeiro documento
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function ($dados) use ($processoId, $empresaId) {
                return $dados['empresa_id'] === $empresaId
                    && $dados['processo_id'] === $processoId
                    && $dados['documento_habilitacao_id'] === 10
                    && $dados['exigido'] === true
                    && $dados['disponivel_envio'] === false
                    && $dados['status'] === 'pendente'
                    && $dados['documento_custom'] === false;
            }));
        
        // Act
        $importados = $this->useCase->executar($processoId, $empresaId);
        
        // Assert
        $this->assertEquals(1, $importados);
    }

    public function test_deve_retornar_zero_quando_todos_documentos_ja_existem(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $documento = new DocumentoHabilitacao();
        $documento->id = 10;
        $documento->empresa_id = $empresaId;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->documentoHabilitacaoRepositoryMock
            ->shouldReceive('buscarAtivosPorEmpresa')
            ->with($empresaId)
            ->once()
            ->andReturn(collect([$documento]));
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('existePorProcessoEDocumento')
            ->with($processoId, 10)
            ->once()
            ->andReturn(true);
        
        // Act
        $importados = $this->useCase->executar($processoId, $empresaId);
        
        // Assert
        $this->assertEquals(0, $importados);
    }

    public function test_deve_lancar_excecao_quando_processo_nao_existe(): void
    {
        // Arrange
        $processoId = 999;
        $empresaId = 1;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Processo não encontrado ou não pertence à empresa.');
        
        $this->useCase->executar($processoId, $empresaId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

