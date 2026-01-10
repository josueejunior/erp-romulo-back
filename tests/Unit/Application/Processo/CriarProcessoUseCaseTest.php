<?php

namespace Tests\Unit\Application\Processo;

use Tests\TestCase;
use App\Domain\Factories\ProcessoFactory;
use Carbon\Carbon;

/**
 * Testes unitários para criação de processos com diferentes status e modalidades
 * 
 * IMPORTANTE: Este teste foi simplificado para focar apenas na criação de entidades Processo
 * com diferentes combinações de status e modalidades, usando a ProcessoFactory.
 * 
 * Para testar o CriarProcessoUseCase completo (incluindo validações de tenant/assinatura),
 * use testes Feature com tenancy real inicializado.
 * 
 * Nota: Os testes unitários de criação de processos com status e modalidades diferentes
 * já estão cobertos em: tests/Unit/Domain/Processo/ProcessoTest.php
 * Este arquivo mantém apenas alguns testes adicionais como referência.
 */
class CriarProcessoUseCaseTest extends TestCase
{
    /**
     * Teste: Verificar que podemos criar processos com diferentes status e modalidades usando a Factory
     * 
     * Este teste usa a ProcessoFactory diretamente, que é o padrão para testes unitários.
     * Para testar o UseCase completo, use testes Feature.
     */
    public function test_deve_criar_processo_com_dados_validos_usando_factory(): void
    {
        // Arrange & Act
        $processo = ProcessoFactory::criarParaTeste([
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Aquisição de materiais',
            'status' => 'participacao',
            'empresa_id' => 1,
        ]);
        
        // Assert
        $this->assertEquals('pregão', $processo->modalidade);
        $this->assertEquals('Aquisição de materiais', $processo->objetoResumido);
        $this->assertEquals('participacao', $processo->status);
        $this->assertEquals(1, $processo->empresaId);
    }

    /**
     * Teste completo: criação de processo com todos os status e modalidades usando Factory
     * 
     * Este teste verifica que é possível criar processos com todas as combinações
     * válidas de status e modalidades (estilos) diferentes usando a ProcessoFactory.
     * 
     * Nota: Este teste é similar ao test_deve_criar_processo_com_todos_status_e_modalidades
     * em tests/Unit/Domain/Processo/ProcessoTest.php, mas está aqui para referência.
     */
    public function test_deve_criar_processo_com_todos_status_e_modalidades_usando_factory(): void
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

        // Act & Assert: Criar processos com todas as combinações usando Factory
        foreach ($todosStatus as $status) {
            foreach ($todasModalidades as $modalidade) {
                $processo = ProcessoFactory::criarParaTeste([
                    'status' => $status,
                    'modalidade' => $modalidade,
                    'objeto_resumido' => "Processo de teste - {$modalidade} em {$status}",
                    'empresa_id' => 1,
                ]);

                // Assert: Verificar que o processo foi criado corretamente
                $this->assertEquals($status, $processo->status, "Status deve ser '{$status}' para modalidade '{$modalidade}'");
                $this->assertEquals($modalidade, $processo->modalidade, "Modalidade deve ser '{$modalidade}' para status '{$status}'");
                $this->assertEquals(1, $processo->empresaId, "Empresa ID deve ser 1");
            }
        }
    }

    /**
     * Teste: criação de processo com status_participacao usando Factory
     */
    public function test_deve_criar_processo_com_status_e_status_participacao_usando_factory(): void
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

        // Act & Assert: Testar status_participacao com diferentes status usando Factory
        foreach ($statusComParticipacao as $status) {
            foreach ($todosStatusParticipacao as $statusParticipacao) {
                $processo = ProcessoFactory::criarParaTeste([
                    'status' => $status,
                    'status_participacao' => $statusParticipacao,
                    'modalidade' => 'pregão',
                    'objeto_resumido' => "Processo {$status} com participação {$statusParticipacao}",
                    'empresa_id' => 1,
                ]);

                // Assert
                $this->assertEquals($status, $processo->status);
                $this->assertEquals($statusParticipacao, $processo->statusParticipacao);
            }
        }
    }

    /**
     * Teste: criação de processos representativos por fase e modalidade usando Factory
     */
    public function test_deve_criar_processos_representativos_por_fase_e_modalidade_usando_factory(): void
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

        // Act & Assert: Criar e validar cada caso representativo usando Factory
        foreach ($casosRepresentativos as $index => $caso) {
            $dados = [
                'status' => $caso['status'],
                'modalidade' => $caso['modalidade'],
                'objeto_resumido' => $caso['descricao'],
                'empresa_id' => 1,
            ];

            // Adicionar campos opcionais se presentes
            if (isset($caso['status_participacao'])) {
                $dados['status_participacao'] = $caso['status_participacao'];
            }
            if (isset($caso['data_recebimento_pagamento'])) {
                $dados['data_recebimento_pagamento'] = $caso['data_recebimento_pagamento']->toDateTimeString();
            }
            if (isset($caso['data_arquivamento'])) {
                $dados['data_arquivamento'] = $caso['data_arquivamento']->toDateTimeString();
            }

            $processo = ProcessoFactory::criarParaTeste($dados);

            // Assert: Validar que o processo foi criado com os dados corretos
            $this->assertEquals($caso['status'], $processo->status, "Caso {$index}: Status deve ser '{$caso['status']}'");
            $this->assertEquals($caso['modalidade'], $processo->modalidade, "Caso {$index}: Modalidade deve ser '{$caso['modalidade']}'");
            $this->assertEquals($caso['descricao'], $processo->objetoResumido, "Caso {$index}: Objeto deve corresponder à descrição");

            if (isset($caso['status_participacao'])) {
                $this->assertEquals($caso['status_participacao'], $processo->statusParticipacao, "Caso {$index}: Status participação deve ser '{$caso['status_participacao']}'");
            }

            if (isset($caso['data_recebimento_pagamento'])) {
                $this->assertInstanceOf(Carbon::class, $processo->dataRecebimentoPagamento, "Caso {$index}: Data recebimento pagamento deve ser Carbon");
                $this->assertEquals($caso['data_recebimento_pagamento']->format('Y-m-d'), $processo->dataRecebimentoPagamento->format('Y-m-d'));
            }

            if (isset($caso['data_arquivamento'])) {
                $this->assertInstanceOf(Carbon::class, $processo->dataArquivamento, "Caso {$index}: Data arquivamento deve ser Carbon");
                $this->assertEquals($caso['data_arquivamento']->format('Y-m-d'), $processo->dataArquivamento->format('Y-m-d'));
            }
        }
    }
}
