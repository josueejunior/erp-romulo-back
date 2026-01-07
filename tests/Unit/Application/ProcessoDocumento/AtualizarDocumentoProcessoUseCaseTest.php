<?php

namespace Tests\Unit\Application\ProcessoDocumento;

use Tests\TestCase;
use App\Application\ProcessoDocumento\UseCases\AtualizarDocumentoProcessoUseCase;
use App\Application\ProcessoDocumento\DTOs\AtualizarDocumentoProcessoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class AtualizarDocumentoProcessoUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private AtualizarDocumentoProcessoUseCase $useCase;
    private $processoRepositoryMock;
    private $processoDocumentoRepositoryMock;
    private $documentoHabilitacaoRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->processoRepositoryMock = Mockery::mock(ProcessoRepositoryInterface::class);
        $this->processoDocumentoRepositoryMock = Mockery::mock(ProcessoDocumentoRepositoryInterface::class);
        $this->documentoHabilitacaoRepositoryMock = Mockery::mock(DocumentoHabilitacaoRepositoryInterface::class);
        
        $this->app->instance(ProcessoRepositoryInterface::class, $this->processoRepositoryMock);
        $this->app->instance(ProcessoDocumentoRepositoryInterface::class, $this->processoDocumentoRepositoryMock);
        $this->app->instance(DocumentoHabilitacaoRepositoryInterface::class, $this->documentoHabilitacaoRepositoryMock);
        
        $this->useCase = new AtualizarDocumentoProcessoUseCase(
            $this->processoRepositoryMock,
            $this->processoDocumentoRepositoryMock,
            $this->documentoHabilitacaoRepositoryMock
        );
    }

    public function test_deve_atualizar_documento_com_dados_validos(): void
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
        $processoDocumento->documento_habilitacao_id = 10;
        $processoDocumento->status = 'pendente';
        $processoDocumento->caminho_arquivo = null;
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([
            'status' => 'anexado',
            'exigido' => true,
        ]);
        
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
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('atualizar')
            ->once()
            ->with($processoDocumento, Mockery::on(function ($dados) {
                return $dados['status'] === 'anexado' && $dados['exigido'] === true;
            }))
            ->andReturn($processoDocumento);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto);
        
        // Assert
        $this->assertInstanceOf(ProcessoDocumento::class, $resultado);
    }

    public function test_deve_validar_arquivo_antes_de_anexar(): void
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
        $processoDocumento->documento_habilitacao_id = 10;
        $processoDocumento->caminho_arquivo = null;
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([]);
        
        // Arquivo muito grande (11MB)
        $arquivo = UploadedFile::fake()->create('documento.pdf', 11 * 1024 * 1024);
        
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
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Arquivo muito grande. Tamanho máximo permitido: 10MB');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto, $arquivo);
    }

    public function test_deve_validar_tipo_de_arquivo(): void
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
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([]);
        
        // Arquivo com tipo não permitido
        $arquivo = UploadedFile::fake()->create('documento.exe', 100);
        
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
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Tipo de arquivo não permitido');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto, $arquivo);
    }

    public function test_deve_anexar_arquivo_valido(): void
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
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([]);
        
        // Arquivo válido (PDF, 1MB)
        $arquivo = UploadedFile::fake()->create('documento.pdf', 1024);
        
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
        
        $this->processoDocumentoRepositoryMock
            ->shouldReceive('atualizar')
            ->once()
            ->with($processoDocumento, Mockery::on(function ($dados) {
                return isset($dados['nome_arquivo'])
                    && isset($dados['caminho_arquivo'])
                    && isset($dados['mime'])
                    && isset($dados['tamanho_bytes'])
                    && $dados['status'] === 'anexado';
            }))
            ->andReturn($processoDocumento);
        
        // Act
        $resultado = $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto, $arquivo);
        
        // Assert
        $this->assertInstanceOf(ProcessoDocumento::class, $resultado);
    }

    public function test_deve_validar_versao_para_documentos_customizados(): void
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
        $processoDocumento->documento_habilitacao_id = null; // Documento customizado
        $processoDocumento->caminho_arquivo = null;
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([
            'versao_documento_habilitacao_id' => 5,
        ]);
        
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
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Documentos customizados não podem ter versão');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto);
    }

    public function test_deve_validar_versao_pertence_ao_documento(): void
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
        $processoDocumento->documento_habilitacao_id = 10;
        $processoDocumento->caminho_arquivo = null;
        
        $dto = AtualizarDocumentoProcessoDTO::fromArray([
            'versao_documento_habilitacao_id' => 999, // Versão que não existe
        ]);
        
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
        
        // Mock versão não encontrada
        DocumentoHabilitacaoVersao::shouldReceive('where')
            ->andReturnSelf();
        DocumentoHabilitacaoVersao::shouldReceive('first')
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Versão do documento não encontrada');
        
        $this->useCase->executar($processoId, $empresaId, $processoDocumentoId, $dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

