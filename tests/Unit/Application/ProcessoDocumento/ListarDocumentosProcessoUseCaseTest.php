<?php

namespace Tests\Unit\Application\ProcessoDocumento;

use Tests\TestCase;
use App\Application\ProcessoDocumento\UseCases\ListarDocumentosProcessoUseCase;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;

class ListarDocumentosProcessoUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ListarDocumentosProcessoUseCase $useCase;
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
        
        $this->useCase = new ListarDocumentosProcessoUseCase(
            $this->processoRepositoryMock,
            $this->processoDocumentoRepositoryMock,
            $this->documentoHabilitacaoRepositoryMock
        );
    }

    public function test_deve_listar_documentos_do_processo_com_sucesso(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $processoDocumento = new ProcessoDocumento();
        $processoDocumento->id = 1;
        $processoDocumento->empresa_id = $empresaId;
        $processoDocumento->processo_id = $processoId;
        $processoDocumento->documento_habilitacao_id = 10;
        $processoDocumento->versao_documento_habilitacao_id = null;
        $processoDocumento->documento_custom = false;
        $processoDocumento->titulo_custom = null;
        $processoDocumento->exigido = true;
        $processoDocumento->disponivel_envio = false;
        $processoDocumento->status = 'pendente';
        $processoDocumento->nome_arquivo = null;
        $processoDocumento->caminho_arquivo = null;
        $processoDocumento->mime = null;
        $processoDocumento->tamanho_bytes = null;
        $processoDocumento->observacoes = null;
        
        $documentoHabilitacao = new DocumentoHabilitacao();
        $documentoHabilitacao->id = 10;
        $documentoHabilitacao->tipo = 'CNPJ';
        $documentoHabilitacao->numero = '12345678000190';
        $documentoHabilitacao->data_validade = null;
        $documentoHabilitacao->status_vencimento = 'sem_data';
        $documentoHabilitacao->dias_para_vencer = null;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('listarPorProcesso')
            ->once()
            ->andReturn(collect([$processoDocumento]));
        
        $this->documentoHabilitacaoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with(10)
            ->once()
            ->andReturn($documentoHabilitacao);
        
        // Mock versões
        $processoDocumento->setRelation('versoes', collect([]));
        $processoDocumento->setRelation('versaoDocumento', null);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId);
        
        // Assert
        $this->assertInstanceOf(Collection::class, $resultado);
        $this->assertCount(1, $resultado);
        
        $documento = $resultado->first();
        $this->assertEquals(1, $documento['id']);
        $this->assertEquals('CNPJ', $documento['tipo']);
        $this->assertEquals('pendente', $documento['status']);
        $this->assertFalse($documento['documento_custom']);
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

    public function test_deve_lancar_excecao_quando_processo_nao_pertence_a_empresa(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = 999; // Empresa diferente
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Processo não encontrado ou não pertence à empresa.');
        
        $this->useCase->executar($processoId, $empresaId);
    }

    public function test_deve_listar_documentos_customizados_sem_versoes(): void
    {
        // Arrange
        $processoId = 1;
        $empresaId = 1;
        
        $processo = new Processo();
        $processo->id = $processoId;
        $processo->empresa_id = $empresaId;
        
        $processoDocumento = new ProcessoDocumento();
        $processoDocumento->id = 1;
        $processoDocumento->empresa_id = $empresaId;
        $processoDocumento->processo_id = $processoId;
        $processoDocumento->documento_habilitacao_id = null; // Documento customizado
        $processoDocumento->versao_documento_habilitacao_id = null;
        $processoDocumento->documento_custom = true;
        $processoDocumento->titulo_custom = 'Certidão Específica';
        $processoDocumento->exigido = true;
        $processoDocumento->disponivel_envio = false;
        $processoDocumento->status = 'pendente';
        $processoDocumento->nome_arquivo = null;
        $processoDocumento->caminho_arquivo = null;
        $processoDocumento->mime = null;
        $processoDocumento->tamanho_bytes = null;
        $processoDocumento->observacoes = null;
        
        $this->processoRepositoryMock
            ->shouldReceive('buscarModeloPorId')
            ->with($processoId)
            ->once()
            ->andReturn($processo);
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('listarPorProcesso')
            ->once()
            ->andReturn(collect([$processoDocumento]));
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId);
        
        // Assert
        $this->assertCount(1, $resultado);
        
        $documento = $resultado->first();
        $this->assertTrue($documento['documento_custom']);
        $this->assertEquals('Certidão Específica', $documento['titulo_custom']);
        $this->assertNull($documento['tipo']);
        $this->assertEmpty($documento['versoes']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

