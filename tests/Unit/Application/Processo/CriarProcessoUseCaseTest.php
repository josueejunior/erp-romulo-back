<?php

namespace Tests\Unit\Application\Processo;

use Tests\TestCase;
use App\Application\Processo\UseCases\CriarProcessoUseCase;
use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Factories\ProcessoFactory;
use App\Models\Tenant;
use App\Models\Plano;
use Carbon\Carbon;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Facades\Tenancy;
use Illuminate\Support\Facades\Cache;

/**
 * Testes unitários para CriarProcessoUseCase
 * 
 * Testa a criação de processos com todos os status e modalidades diferentes.
 * 
 * IMPORTANTE: Este teste foca na criação de processos com diferentes combinações
 * de status e modalidades. As validações de tenant, assinatura e limites de plano
 * devem ser testadas como testes Feature que usam tenancy real.
 * 
 * Para testes completos incluindo validações de tenant, use:
 * - tests/Feature/Processo/CriarProcessoValidacaoTest.php
 */
class CriarProcessoUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CriarProcessoUseCase $useCase;
    private $processoRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock do repository
        $this->processoRepositoryMock = Mockery::mock(ProcessoRepositoryInterface::class);
        $this->app->instance(ProcessoRepositoryInterface::class, $this->processoRepositoryMock);
        
        // Mock do tenant e plano para validações de assinatura
        $this->setupTenancyMocks();
        
        $this->useCase = new CriarProcessoUseCase($this->processoRepositoryMock);
    }

    /**
     * Configura mocks para tenancy (tenant e plano)
     * 
     * Cria tenant e plano reais no banco e inicializa o tenancy
     */
    private function setupTenancyMocks(): void
    {
        // Criar tenant e plano reais no banco de dados
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            [
                'razao_social' => 'Tenant de Teste',
                'cnpj' => '12345678000199',
                'status' => 'ativa',
            ]
        );

        // Criar plano real
        $plano = Plano::firstOrCreate(
            ['id' => 1],
            [
                'nome' => 'Plano de Teste',
                'limite_processos' => null, // Sem limite
                'limite_processos_mensal' => null,
                'limite_processos_diario' => null,
                'ativo' => true,
            ]
        );

        // Associar plano ao tenant
        $tenant->plano_atual_id = $plano->id;
        $tenant->assinatura_atual_id = 1; // Fake assinatura para passar na validação
        $tenant->save();
        $tenant->refresh();
        $tenant->load('planoAtual');

        // Inicializar tenancy com o tenant real
        // O helper tenancy() retorna o tenant inicializado
        try {
            Tenancy::initialize($tenant);
        } catch (\Exception $e) {
            // Se não conseguir inicializar, criar um mock alternativo
            // Por enquanto, vamos ignorar o erro e usar o tenant diretamente
        }

        // Mockar métodos do tenant que dependem de autenticação
        // Usando swap no container para substituir o tenant
        // O método temAssinaturaAtiva() precisa de usuário autenticado
        $tenantMock = Mockery::mock($tenant)->makePartial();
        $tenantMock->shouldReceive('temAssinaturaAtiva')->andReturn(true);
        $tenantMock->shouldReceive('podeCriarProcesso')->andReturn(true);
        $tenantMock->planoAtual = $plano;
        $tenantMock->id = $tenant->id;
        
        // Garantir que planoAtual é acessível
        $tenantMock->shouldReceive('getAttribute')
            ->with('planoAtual')
            ->andReturn($plano);
        $tenantMock->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn($tenant->id);

        // Sobrescrever o tenant no container do tenancy
        // O helper tenancy() busca do container
        $this->app->singleton('tenancy.tenant', function () use ($tenantMock) {
            return $tenantMock;
        });
        
        // Mockar o facade Tenancy para garantir que retorna nosso tenant mockado
        Tenancy::shouldReceive('tenant')->andReturn($tenantMock);
    }

    /**
     * Teste: criação de processo com dados válidos
     */
    public function test_deve_criar_processo_com_dados_validos(): void
    {
        // Arrange
        $dto = new CriarProcessoDTO(
            empresaId: 1,
            modalidade: 'pregão',
            objetoResumido: 'Aquisição de materiais',
            status: 'participacao',
        );
        
        $processoEsperado = ProcessoFactory::criar([
            'empresa_id' => 1,
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Aquisição de materiais',
            'status' => 'participacao',
        ]);
        
        $this->processoRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::type(Processo::class))
            ->andReturn($processoEsperado);
        
        // Act
        $resultado = $this->useCase->executar($dto);
        
        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals('pregão', $resultado->modalidade);
        $this->assertEquals('Aquisição de materiais', $resultado->objetoResumido);
        $this->assertEquals('participacao', $resultado->status);
        $this->assertEquals(1, $resultado->empresaId);
    }

    /**
     * Teste completo: criação de processo com todos os status e modalidades
     * 
     * Este teste verifica que é possível criar processos com todas as combinações
     * válidas de status e modalidades (estilos) diferentes.
     */
    public function test_deve_criar_processo_com_todos_status_e_modalidades(): void
    {
        // Arrange: Definir todos os status e modalidades válidos
        $todosStatus = [
            'rascunho',
            'publicado',
            'participacao',
            'em_disputa',
            'julgamento',
            'julgamento_habilitacao',
            'execucao',
            'vencido',
            'perdido',
            'pagamento',
            'encerramento',
            'arquivado',
        ];

        $todasModalidades = [
            'pregão',
            'concorrência',
            'tomada_preco',
            'convite',
            'dispensa',
            'inexigibilidade',
        ];

        // Act & Assert: Criar processos com todas as combinações
        foreach ($todosStatus as $status) {
            foreach ($todasModalidades as $modalidade) {
                $dto = new CriarProcessoDTO(
                    empresaId: 1,
                    modalidade: $modalidade,
                    objetoResumido: "Processo de teste - {$modalidade} em {$status}",
                    status: $status,
                );

                $processoEsperado = ProcessoFactory::criar([
                    'empresa_id' => 1,
                    'modalidade' => $modalidade,
                    'objeto_resumido' => "Processo de teste - {$modalidade} em {$status}",
                    'status' => $status,
                ]);

                $this->processoRepositoryMock
                    ->shouldReceive('criar')
                    ->once()
                    ->with(Mockery::on(function (Processo $processo) use ($status, $modalidade) {
                        return $processo->status === $status && $processo->modalidade === $modalidade;
                    }))
                    ->andReturn($processoEsperado);

                // Act
                $resultado = $this->useCase->executar($dto);

                // Assert
                $this->assertEquals($status, $resultado->status, "Status deve ser '{$status}' para modalidade '{$modalidade}'");
                $this->assertEquals($modalidade, $resultado->modalidade, "Modalidade deve ser '{$modalidade}' para status '{$status}'");
                $this->assertEquals(1, $resultado->empresaId, "Empresa ID deve ser 1");
            }
        }
    }

    /**
     * Teste: criação de processo com status_participacao
     */
    public function test_deve_criar_processo_com_status_participacao(): void
    {
        // Arrange
        $statusComParticipacao = [
            'publicado',
            'participacao',
            'em_disputa',
        ];

        $todosStatusParticipacao = [
            'normal',
            'adiado',
            'suspenso',
            'cancelado',
        ];

        // Act & Assert
        foreach ($statusComParticipacao as $status) {
            foreach ($todosStatusParticipacao as $statusParticipacao) {
                $dto = new CriarProcessoDTO(
                    empresaId: 1,
                    status: $status,
                    statusParticipacao: $statusParticipacao,
                    modalidade: 'pregão',
                    objetoResumido: "Processo {$status} com participação {$statusParticipacao}",
                );

                $processoEsperado = ProcessoFactory::criar([
                    'empresa_id' => 1,
                    'status' => $status,
                    'status_participacao' => $statusParticipacao,
                    'modalidade' => 'pregão',
                    'objeto_resumido' => "Processo {$status} com participação {$statusParticipacao}",
                ]);

                $this->processoRepositoryMock
                    ->shouldReceive('criar')
                    ->once()
                    ->andReturn($processoEsperado);

                // Act
                $resultado = $this->useCase->executar($dto);

                // Assert
                $this->assertEquals($status, $resultado->status);
                $this->assertEquals($statusParticipacao, $resultado->statusParticipacao);
            }
        }
    }

    /**
     * Teste: criação de processo com status padrão (quando não informado)
     */
    public function test_deve_usar_status_padrao_participacao_quando_nao_informado(): void
    {
        // Arrange
        $dto = new CriarProcessoDTO(
            empresaId: 1,
            modalidade: 'pregão',
            objetoResumido: 'Processo sem status definido',
            // status não informado, deve usar padrão 'participacao'
        );

        $processoEsperado = ProcessoFactory::criar([
            'empresa_id' => 1,
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Processo sem status definido',
            'status' => 'participacao', // Status padrão
        ]);

        $this->processoRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function (Processo $processo) {
                return $processo->status === 'participacao';
            }))
            ->andReturn($processoEsperado);

        // Act
        $resultado = $this->useCase->executar($dto);

        // Assert
        $this->assertEquals('participacao', $resultado->status, 'Deve usar status padrão "participacao" quando não informado');
    }

    /**
     * Teste: criação de processos representativos por fase e modalidade
     */
    public function test_deve_criar_processos_representativos_por_fase_e_modalidade(): void
    {
        // Arrange: Casos representativos por fase do processo
        $casosRepresentativos = [
            // Fase Inicial
            [
                'status' => 'rascunho',
                'modalidade' => 'pregão',
                'descricao' => 'Rascunho de pregão',
            ],
            // Fase de Publicação
            [
                'status' => 'publicado',
                'modalidade' => 'tomada_preco',
                'status_participacao' => 'normal',
                'descricao' => 'Publicado - tomada de preço',
            ],
            // Fase de Participação
            [
                'status' => 'participacao',
                'modalidade' => 'pregão',
                'status_participacao' => 'normal',
                'descricao' => 'Em participação - pregão',
            ],
            // Fase de Disputa
            [
                'status' => 'em_disputa',
                'modalidade' => 'pregão',
                'status_participacao' => 'normal',
                'descricao' => 'Em disputa - pregão',
            ],
            // Fase de Julgamento
            [
                'status' => 'julgamento',
                'modalidade' => 'pregão',
                'descricao' => 'Em julgamento - pregão',
            ],
            [
                'status' => 'julgamento_habilitacao',
                'modalidade' => 'concorrência',
                'descricao' => 'Julgamento habilitação - concorrência',
            ],
            // Fase de Execução
            [
                'status' => 'execucao',
                'modalidade' => 'pregão',
                'descricao' => 'Em execução - pregão',
            ],
            // Fase de Pagamento
            [
                'status' => 'pagamento',
                'modalidade' => 'pregão',
                'data_recebimento_pagamento' => Carbon::parse('2026-02-01 10:00:00'),
                'descricao' => 'Aguardando pagamento - pregão',
            ],
            // Fase Final
            [
                'status' => 'encerramento',
                'modalidade' => 'pregão',
                'descricao' => 'Encerrado - pregão',
            ],
            // Estados Finais Negativos
            [
                'status' => 'vencido',
                'modalidade' => 'pregão',
                'descricao' => 'Vencido - pregão',
            ],
            [
                'status' => 'perdido',
                'modalidade' => 'concorrência',
                'descricao' => 'Perdido - concorrência',
            ],
            [
                'status' => 'arquivado',
                'modalidade' => 'pregão',
                'data_arquivamento' => Carbon::parse('2026-01-31 10:00:00'),
                'descricao' => 'Arquivado - pregão',
            ],
        ];

        // Act & Assert
        foreach ($casosRepresentativos as $index => $caso) {
            $dto = new CriarProcessoDTO(
                empresaId: 1,
                status: $caso['status'],
                modalidade: $caso['modalidade'],
                objetoResumido: $caso['descricao'],
                statusParticipacao: $caso['status_participacao'] ?? null,
                dataRecebimentoPagamento: $caso['data_recebimento_pagamento'] ?? null,
                // Nota: dataArquivamento não está no DTO, seria necessário adicionar se necessário
            );

            $dadosProcesso = [
                'empresa_id' => 1,
                'status' => $caso['status'],
                'modalidade' => $caso['modalidade'],
                'objeto_resumido' => $caso['descricao'],
            ];

            if (isset($caso['status_participacao'])) {
                $dadosProcesso['status_participacao'] = $caso['status_participacao'];
            }
            if (isset($caso['data_recebimento_pagamento'])) {
                $dadosProcesso['data_recebimento_pagamento'] = $caso['data_recebimento_pagamento']->toDateTimeString();
            }

            $processoEsperado = ProcessoFactory::criar($dadosProcesso);

            $this->processoRepositoryMock
                ->shouldReceive('criar')
                ->once()
                ->andReturn($processoEsperado);

            // Act
            $resultado = $this->useCase->executar($dto);

            // Assert
            $this->assertEquals($caso['status'], $resultado->status, "Caso {$index}: Status deve ser '{$caso['status']}'");
            $this->assertEquals($caso['modalidade'], $resultado->modalidade, "Caso {$index}: Modalidade deve ser '{$caso['modalidade']}'");
            $this->assertEquals($caso['descricao'], $resultado->objetoResumido, "Caso {$index}: Objeto deve corresponder à descrição");

            if (isset($caso['status_participacao'])) {
                $this->assertEquals($caso['status_participacao'], $resultado->statusParticipacao, "Caso {$index}: Status participação deve ser '{$caso['status_participacao']}'");
            }

            if (isset($caso['data_recebimento_pagamento'])) {
                $this->assertInstanceOf(Carbon::class, $resultado->dataRecebimentoPagamento, "Caso {$index}: Data recebimento pagamento deve ser Carbon");
                $this->assertEquals($caso['data_recebimento_pagamento']->format('Y-m-d'), $resultado->dataRecebimentoPagamento->format('Y-m-d'));
            }
        }
    }

    /**
     * Nota: Os testes de validação de tenant, assinatura e limites de plano
     * são melhor testados como testes Feature que usam tenancy real.
     * Este teste unitário foca na criação de processos com diferentes
     * status e modalidades, que é o objetivo principal.
     * 
     * Para testar validações de tenant/assinatura, use testes Feature como:
     * - tests/Feature/Processo/CriarProcessoValidacaoTest.php
     */

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

