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
        $this->expectExceptionMessage('empresa_id é obrigatório');
        
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
}

