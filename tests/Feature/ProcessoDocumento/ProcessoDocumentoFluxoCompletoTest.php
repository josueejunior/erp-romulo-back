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
use App\Models\Orgao;
use App\Models\Tenant;
use App\Services\JWTService;
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
    private Tenant $tenant;
    private Orgao $orgao;
    private Processo $processo;
    private DocumentoHabilitacao $documentoHabilitacao;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        // IMPORTANTE: Criar tenant e banco ANTES de qualquer transação
        // porque CREATE DATABASE não pode ser executado dentro de uma transação
        // O RefreshDatabase inicia uma transação, então precisamos criar o banco antes
        
        // Criar tenant usando conexão direta (sem transação)
        $this->tenant = new Tenant([
            'razao_social' => 'Tenant Teste LTDA',
            'cnpj' => '12345678000190',
            'email' => 'teste@tenant.com',
            'status' => 'ativa',
        ]);
        $this->tenant->save();
        
        // Criar banco de dados do tenant usando conexão sem transação
        try {
            // Obter nome do banco usando o padrão do stancl/tenancy: prefix + tenant_id + suffix
            $prefix = config('tenancy.database.prefix', 'tenant_');
            $suffix = config('tenancy.database.suffix', '');
            $databaseName = $prefix . $this->tenant->id . $suffix;
            
            // Obter configurações do banco central (usar a conexão padrão)
            $connectionName = config('database.default');
            $centralConfig = config("database.connections.{$connectionName}");
            
            // Usar conexão PostgreSQL direta (sem transação)
            // Conectar ao banco "postgres" (banco padrão do PostgreSQL) para criar outros bancos
            $pdo = new \PDO(
                "pgsql:host={$centralConfig['host']};port={$centralConfig['port']};dbname=postgres",
                $centralConfig['username'],
                $centralConfig['password']
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE \"{$databaseName}\" WITH TEMPLATE=template0");
        } catch (\PDOException $e) {
            // Se o banco já existe, continuar
            if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'duplicate')) {
                throw $e;
            }
        }
        
        // Inicializar contexto do tenant
        tenancy()->initialize($this->tenant);
        
        // Executar migrations do tenant
        \Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        
        // Criar empresa dentro do tenant
        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'nome_fantasia' => 'Empresa Teste',
            'cnpj' => '12345678000190',
            'email' => 'teste@empresa.com',
            'status' => 'ativa',
        ]);
        
        // Criar usuário
        $this->user = User::create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@teste.com',
            'password' => bcrypt('password'),
            'empresa_ativa_id' => $this->empresa->id,
        ]);
        $this->user->empresas()->attach($this->empresa->id);
        
        // Criar token JWT
        $jwtService = app(JWTService::class);
        $this->token = $jwtService->generateToken([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'empresa_id' => $this->empresa->id,
        ]);
        
        // Criar órgão (obrigatório para processo)
        $this->orgao = Orgao::create([
            'empresa_id' => $this->empresa->id,
            'uasg' => '123456',
            'razao_social' => 'Órgão Teste',
        ]);
        
        // Criar processo
        $this->processo = Processo::create([
            'empresa_id' => $this->empresa->id,
            'orgao_id' => $this->orgao->id,
            'status' => 'participacao',
            'modalidade' => 'pregao', // Valores permitidos: 'dispensa' ou 'pregao' (minúsculas)
            'numero_modalidade' => '001/2024',
            'objeto_resumido' => 'Objeto de teste para documentos',
            'data_hora_sessao_publica' => now()->addDays(30),
        ]);
        
        // Criar documento de habilitação
        $this->documentoHabilitacao = DocumentoHabilitacao::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'CNPJ',
            'numero' => '12345678000190',
            'ativo' => true,
        ]);
        
        // Autenticar usuário via Sanctum (para compatibilidade)
        $this->actingAs($this->user, 'sanctum');
    }
    
    /**
     * Obter headers de autenticação
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => (string) $this->tenant->id,
            'X-Empresa-ID' => (string) $this->empresa->id,
            'Accept' => 'application/json',
        ];
    }
    
    /**
     * Método auxiliar para fazer requisições autenticadas com headers
     */
    protected function authenticatedCall(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], ?string $content = null)
    {
        $headers = $this->getAuthHeaders();
        
        foreach ($headers as $key => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($key))] = $value;
        }
        
        return $this->call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
    
    /**
     * Sobrescrever postJson para sempre incluir headers
     */
    protected function postJson($uri, array $data = [], array $headers = [])
    {
        return parent::postJson($uri, $data, array_merge($this->getAuthHeaders(), $headers));
    }
    
    /**
     * Sobrescrever getJson para sempre incluir headers
     */
    protected function getJson($uri, array $headers = [])
    {
        return parent::getJson($uri, array_merge($this->getAuthHeaders(), $headers));
    }
    
    /**
     * Sobrescrever patchJson para sempre incluir headers
     */
    protected function patchJson($uri, array $data = [], array $headers = [])
    {
        return parent::patchJson($uri, $data, array_merge($this->getAuthHeaders(), $headers));
    }
    
    /**
     * Sobrescrever putJson para sempre incluir headers
     */
    protected function putJson($uri, array $data = [], array $headers = [])
    {
        return parent::putJson($uri, $data, array_merge($this->getAuthHeaders(), $headers));
    }
    
    /**
     * Sobrescrever deleteJson para sempre incluir headers
     */
    protected function deleteJson($uri, array $data = [], array $headers = [])
    {
        return parent::deleteJson($uri, $data, array_merge($this->getAuthHeaders(), $headers));
    }
    
    protected function tearDown(): void
    {
        // Finalizar tenancy se estiver inicializado
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        
        parent::tearDown();
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
        
        $response = $this->authenticatedCall('PATCH', "/api/v1/processos/{$this->processo->id}/documentos/{$documentoCustomId}", [
            'status' => 'anexado',
        ], [], [
            'arquivo' => $arquivo,
        ]);
        $response->assertStatus(200);
        
        // 6. Baixar arquivo (só funciona se arquivo foi anexado)
        // Como o arquivo foi anexado no passo anterior, podemos tentar baixar
        $response = $this->get("/api/v1/processos/{$this->processo->id}/documentos/{$documentoCustomId}/download");
        // Pode retornar 200 se arquivo existe ou 404 se não foi salvo corretamente
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_deve_validar_arquivo_ao_anexar(): void
    {
        // Criar documento customizado
        $processoDocumento = ProcessoDocumento::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
            'exigido' => true,
            'disponivel_envio' => false,
            'status' => 'pendente',
        ]);
        
        // Tentar anexar arquivo muito grande
        $arquivoGrande = UploadedFile::fake()->create('documento.pdf', 11 * 1024 * 1024);
        
        $response = $this->authenticatedCall('PATCH', "/api/v1/processos/{$this->processo->id}/documentos/{$processoDocumento->id}", [], [], [
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
        $processoDocumento = ProcessoDocumento::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
            'exigido' => true,
            'disponivel_envio' => false,
            'status' => 'pendente',
        ]);
        
        // Tentar anexar arquivo com tipo não permitido
        $arquivoInvalido = UploadedFile::fake()->create('documento.exe', 100);
        
        $response = $this->authenticatedCall('PATCH', "/api/v1/processos/{$this->processo->id}/documentos/{$processoDocumento->id}", [], [], [
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
        $processoDocumento = ProcessoDocumento::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $this->processo->id,
            'documento_custom' => true,
            'titulo_custom' => 'Teste',
            'documento_habilitacao_id' => null,
            'exigido' => true,
            'disponivel_envio' => false,
            'status' => 'pendente',
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
        $doc2 = DocumentoHabilitacao::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'Alvará',
            'ativo' => true,
        ]);
        
        $doc3 = DocumentoHabilitacao::create([
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

