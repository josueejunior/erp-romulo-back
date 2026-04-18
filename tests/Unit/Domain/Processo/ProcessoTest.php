<?php

namespace Tests\Unit\Domain\Processo;

use Tests\TestCase;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Factories\ProcessoFactory;
use Carbon\Carbon;

class ProcessoTest extends TestCase
{
    public function test_deve_criar_processo_com_dados_validos(): void
    {
        // Arrange & Act
        $processo = ProcessoFactory::criarParaTeste([
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Aquisição de materiais',
            'status' => 'rascunho',
            'empresa_id' => 1,
        ]);
        
        // Assert
        $this->assertEquals('pregão', $processo->modalidade);
        $this->assertEquals('Aquisição de materiais', $processo->objetoResumido);
        $this->assertEquals('rascunho', $processo->status);
    }

    public function test_deve_exigir_empresa_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empresa_id é obrigatório para criar um Processo');
        
        ProcessoFactory::criar([
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Teste',
            // empresa_id não fornecido
        ]);
    }

    public function test_deve_aceitar_status_validos(): void
    {
        $statusValidos = ['rascunho', 'participacao', 'julgamento_habilitacao', 'execucao', 'pagamento', 'encerramento'];
        
        foreach ($statusValidos as $status) {
            $processo = ProcessoFactory::criarParaTeste([
                'status' => $status,
                'empresa_id' => 1,
            ]);
            
            $this->assertEquals($status, $processo->status);
        }
    }

    public function test_deve_aceitar_modalidades_validas(): void
    {
        $modalidades = ['pregão', 'concorrência', 'tomada_preco', 'convite', 'dispensa', 'inexigibilidade'];
        
        foreach ($modalidades as $modalidade) {
            $processo = ProcessoFactory::criarParaTeste([
                'modalidade' => $modalidade,
                'empresa_id' => 1,
            ]);
            
            $this->assertEquals($modalidade, $processo->modalidade);
        }
    }

    public function test_deve_aceitar_srp_boolean(): void
    {
        $processoSrp = ProcessoFactory::criarParaTeste([
            'srp' => true,
            'empresa_id' => 1,
        ]);
        
        $processoNaoSrp = ProcessoFactory::criarParaTeste([
            'srp' => false,
            'empresa_id' => 1,
        ]);
        
        $this->assertTrue($processoSrp->srp);
        $this->assertFalse($processoNaoSrp->srp);
    }

    public function test_deve_parsear_data_sessao_publica(): void
    {
        $data = '2026-01-15 10:00:00';
        
        $processo = ProcessoFactory::criarParaTeste([
            'data_hora_sessao_publica' => $data,
            'empresa_id' => 1,
        ]);
        
        $this->assertInstanceOf(Carbon::class, $processo->dataHoraSessaoPublica);
        $this->assertEquals('2026-01-15', $processo->dataHoraSessaoPublica->format('Y-m-d'));
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
                // Testar criação com cada combinação status + modalidade
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
     * Teste: criação de processo com todos os status e diferentes status_participacao
     */
    public function test_deve_criar_processo_com_status_e_status_participacao(): void
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

        // Act & Assert: Testar status_participacao com diferentes status
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
     * Teste: criação de processos com diferentes estilos (modalidades) e status específicos
     * 
     * Este teste cria processos representativos de cada fase do ciclo de vida
     * combinado com diferentes modalidades para garantir cobertura completa.
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
            [
                'status' => 'rascunho',
                'modalidade' => 'concorrência',
                'descricao' => 'Rascunho de concorrência',
            ],
            // Fase de Publicação
            [
                'status' => 'publicado',
                'modalidade' => 'tomada_preco',
                'status_participacao' => 'normal',
                'descricao' => 'Publicado - tomada de preço',
            ],
            [
                'status' => 'publicado',
                'modalidade' => 'convite',
                'status_participacao' => 'adiado',
                'descricao' => 'Publicado - convite adiado',
            ],
            // Fase de Participação
            [
                'status' => 'participacao',
                'modalidade' => 'pregão',
                'status_participacao' => 'normal',
                'descricao' => 'Em participação - pregão',
            ],
            [
                'status' => 'participacao',
                'modalidade' => 'dispensa',
                'status_participacao' => 'suspenso',
                'descricao' => 'Em participação - dispensa suspensa',
            ],
            // Fase de Disputa
            [
                'status' => 'em_disputa',
                'modalidade' => 'pregão',
                'status_participacao' => 'normal',
                'descricao' => 'Em disputa - pregão',
            ],
            [
                'status' => 'em_disputa',
                'modalidade' => 'concorrência',
                'status_participacao' => 'cancelado',
                'descricao' => 'Em disputa - concorrência cancelada',
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
            [
                'status' => 'execucao',
                'modalidade' => 'inexigibilidade',
                'descricao' => 'Em execução - inexigibilidade',
            ],
            // Fase de Pagamento
            [
                'status' => 'pagamento',
                'modalidade' => 'pregão',
                'data_recebimento_pagamento' => '2026-02-01 10:00:00',
                'descricao' => 'Aguardando pagamento - pregão',
            ],
            [
                'status' => 'pagamento',
                'modalidade' => 'tomada_preco',
                'descricao' => 'Aguardando pagamento - tomada de preço',
            ],
            // Fase Final
            [
                'status' => 'encerramento',
                'modalidade' => 'pregão',
                'descricao' => 'Encerrado - pregão',
            ],
            [
                'status' => 'encerramento',
                'modalidade' => 'concorrência',
                'descricao' => 'Encerrado - concorrência',
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
                'data_arquivamento' => '2026-01-31 10:00:00',
                'descricao' => 'Arquivado - pregão',
            ],
        ];

        // Act & Assert: Criar e validar cada caso representativo
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
                $dados['data_recebimento_pagamento'] = $caso['data_recebimento_pagamento'];
            }
            if (isset($caso['data_arquivamento'])) {
                $dados['data_arquivamento'] = $caso['data_arquivamento'];
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
            }

            if (isset($caso['data_arquivamento'])) {
                $this->assertInstanceOf(Carbon::class, $processo->dataArquivamento, "Caso {$index}: Data arquivamento deve ser Carbon");
            }
        }
    }
}

