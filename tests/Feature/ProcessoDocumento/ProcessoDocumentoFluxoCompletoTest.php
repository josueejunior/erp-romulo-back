<?php

namespace Tests\Feature\ProcessoDocumento;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;
use App\Modules\Auth\Models\User;
use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Teste de integração para o fluxo completo de documentos do processo
 * 
 * Testa o fluxo completo:
 * 1. Criar processo
 * 2. Importar documentos
 * 3. Listar documentos
 * 4. Atualizar documento
 * 5. Criar documento customizado
 * 6. Anexar arquivo
 * 7. Baixar arquivo
 */
class ProcessoDocumentoFluxoCompletoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Empresa $empresa;
    private Processo $processo;
    private DocumentoHabilitacao $documentoHabilitacao;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        // Criar empresa
        $this->empresa = Empresa::factory()->create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '12345678000190',
        ]);
        
        // Criar usuário
        $this->user = User::factory()->create([
            'empresa_ativa_id' => $this->empresa->id,
        ]);
        $this->user->empresas()->attach($this->empresa->id);
        
        // Criar processo
        $this->processo = Processo::factory()->create([
            'empresa_id' => $this->empresa->id,
            'status' => 'participacao',
        ]);
        
        // Criar documento de habilitação
        $this->documentoHabilitacao = DocumentoHabilitacao::factory()->create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'CNPJ',
            'numero' => '12345678000190',
            'ativo' => true,
        ]);
        
        // Autenticar usuário
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_fluxo_completo_documentos_processo(): void
    {
        // 1. Importar documentos ativos
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/importar");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'importados',
        ]);
        
        $this->assertGreaterThan(0, $response->json('importados'));
        
        // 2. Listar documentos
        $response = $this->getJson("/api/v1/processos/{$this->processo->id}/documentos");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'tipo',
                    'status',
                    'exigido',
                    'disponivel_envio',
                ],
            ],
        ]);
        
        $documentos = $response->json('data');
        $this->assertNotEmpty($documentos);
        
        $primeiroDocumento = $documentos[0];
        $documentoId = $primeiroDocumento['id'];
        
        // 3. Atualizar documento
        $response = $this->patchJson("/api/v1/processos/{$this->processo->id}/documentos/{$documentoId}", [
            'status' => 'possui',
            'exigido' => true,
            'disponivel_envio' => true,
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        
        // 4. Criar documento customizado
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/custom", [
            'titulo_custom' => 'Certidão Específica',
            'exigido' => true,
            'disponivel_envio' => false,
            'status' => 'pendente',
        ]);
        $response->assertStatus(201);
        $response->assertJsonStructure(['data']);
        
        $documentoCustom = $response->json('data');
        $documentoCustomId = $documentoCustom['id'];
        
        // 5. Anexar arquivo ao documento customizado
        $arquivo = UploadedFile::fake()->create('certidao.pdf', 1024);
        
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/{$documentoCustomId}", [
            'arquivo' => $arquivo,
            'status' => 'anexado',
        ]);
        $response->assertStatus(200);
        
        // 6. Baixar arquivo
        $response = $this->getJson("/api/v1/processos/{$this->processo->id}/documentos/{$documentoCustomId}/download");
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_deve_validar_arquivo_ao_anexar(): void
    {
        // Criar documento customizado
        $processoDocumento = ProcessoDocumento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
        ]);
        
        // Tentar anexar arquivo muito grande
        $arquivoGrande = UploadedFile::fake()->create('documento.pdf', 11 * 1024 * 1024);
        
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/{$processoDocumento->id}", [
            'arquivo' => $arquivoGrande,
        ]);
        
        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Arquivo muito grande. Tamanho máximo permitido: 10MB',
        ]);
    }

    public function test_deve_validar_tipo_de_arquivo(): void
    {
        // Criar documento customizado
        $processoDocumento = ProcessoDocumento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
        ]);
        
        // Tentar anexar arquivo com tipo não permitido
        $arquivoInvalido = UploadedFile::fake()->create('documento.exe', 100);
        
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/{$processoDocumento->id}", [
            'arquivo' => $arquivoInvalido,
        ]);
        
        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Tipo de arquivo não permitido',
        ]);
    }

    public function test_deve_validar_documento_customizado_nao_pode_ter_versao(): void
    {
        // Criar documento customizado
        $processoDocumento = ProcessoDocumento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
            'documento_habilitacao_id' => null,
        ]);
        
        // Tentar atribuir versão a documento customizado
        $response = $this->patchJson("/api/v1/processos/{$this->processo->id}/documentos/{$processoDocumento->id}", [
            'versao_documento_habilitacao_id' => 1,
        ]);
        
        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Documentos customizados não podem ter versão',
        ]);
    }

    public function test_deve_sincronizar_documentos_selecionados(): void
    {
        // Criar mais documentos de habilitação
        $doc2 = DocumentoHabilitacao::factory()->create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'Alvará',
            'ativo' => true,
        ]);
        
        $doc3 = DocumentoHabilitacao::factory()->create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'Licença',
            'ativo' => true,
        ]);
        
        // Sincronizar apenas doc1 e doc2
        $response = $this->postJson("/api/v1/processos/{$this->processo->id}/documentos/sincronizar", [
            'documentos' => [
                $this->documentoHabilitacao->id => [
                    'exigido' => true,
                    'disponivel_envio' => false,
                    'status' => 'pendente',
                ],
                $doc2->id => [
                    'exigido' => true,
                    'disponivel_envio' => true,
                    'status' => 'possui',
                ],
            ],
        ]);
        
        $response->assertStatus(200);
        
        // Verificar que apenas doc1 e doc2 foram vinculados
        $response = $this->getJson("/api/v1/processos/{$this->processo->id}/documentos");
        $documentos = $response->json('data');
        
        $this->assertCount(2, $documentos);
        $tipos = collect($documentos)->pluck('tipo')->toArray();
        $this->assertContains('CNPJ', $tipos);
        $this->assertContains('Alvará', $tipos);
        $this->assertNotContains('Licença', $tipos);
    }
}

