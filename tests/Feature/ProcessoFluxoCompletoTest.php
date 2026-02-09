<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Auth\Models\User;
use App\Models\Empresa; // Assuming this exists based on search
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Modules\Processo\Services\ProcessoItemVinculoService;
use Carbon\Carbon;

class ProcessoFluxoCompletoTest extends TestCase
{
    use RefreshDatabase;

    private $service;
    private $empresa;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create context
        // Ensure migration runs or we are in memory
        $this->empresa = new Empresa();
        $this->empresa->forceFill([
            'id' => 1,
            'nome' => 'Nova Empresa',
            'cnpj' => '12345678000199',
            'active' => true
        ])->save();

        $this->user = User::factory()->create();
        
        $this->service = app(ProcessoItemVinculoService::class);
    }

    public function test_fluxo_completo_processo_a_nota_fiscal()
    {
        // 1. Criar Processo
        $processo = Processo::create([
            'empresa_id' => $this->empresa->id,
            'numero' => 'PROC-2024-001',
            'objeto' => 'Aquisição de Teste',
            'status' => 'execucao', // Must be execucao for NFs usually
            'modalidade' => 'pregao_eletronico',
            'data_inicio' => now(),
        ]);

        // 2. Criar Item (Qtd: 100, Valor: 10.00)
        $item = ProcessoItem::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $processo->id,
            'numero_item' => 1,
            'quantidade' => 100,
            'valor_estimado' => 10.00,
            'unidade' => 'UN',
            'especificacao_tecnica' => 'Item de Teste',
            'status_item' => 'aceito' // Active
        ]);
        
        $this->assertEquals(100, $item->quantidade);

        // 3. Criar Contrato (Vincula 50 itens)
        // O contrato cobre metade do item.
        $contrato = Contrato::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $processo->id,
            'numero' => 'CT-001',
            'data_inicio' => now(),
            'data_fim' => now()->addYear(),
            'valor_total' => 500.00, // 50 * 10
        ]);

        // Vincular Item ao Contrato via Service
        $vinculoContrato = $this->service->store($processo, $item, [
            'processo_item_id' => $item->id,
            'contrato_id' => $contrato->id,
            'quantidade' => 50,
            'valor_unitario' => 10.00,
            'valor_total' => 500.00,
        ], $this->empresa->id);

        $this->assertNotNull($vinculoContrato);
        $this->assertEquals(50, $item->fresh()->quantidade_vinculada); // Depende se getQuantidadeVinculadaAttribute soma Contratos

        // 4. Criar Empenho (Vincula 20 itens DO CONTRA TO)
        $empenho = Empenho::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $processo->id,
            'contrato_id' => $contrato->id,
            'numero' => 'EMP-001',
            'data' => now(),
            'valor' => 200.00, // 20 * 10
        ]);

        // Vincular Item ao Empenho + Contrato
        $vinculoEmpenho = $this->service->store($processo, $item, [
            'processo_item_id' => $item->id,
            'empenho_id' => $empenho->id,
            'contrato_id' => $contrato->id, // Herda do empenho
            'quantidade' => 20,
            'valor_unitario' => 10.00,
            'valor_total' => 200.00,
        ], $this->empresa->id);

        $this->assertNotNull($vinculoEmpenho);
        
        // Verify Logic: Do we double count?
        // Contrato Vinculo: 50.
        // Empenho Vinculo: 20.
        // If Logic sums ALL vinculos, total is 70.
        // Ideally, Empenho consumes Contrato balance, not Item balance directly?
        // ProcessoItemVinculoService logic says: "Se tem Empenho... e nao tem NF... ignorar validação de limites dos pais".
        // This implies Hierarchy validation is:
        // Quantity requested (20) <= Available for Contrato?
        
        // 5. Criar Nota Fiscal de SAÍDA (Venda/Entrega) (Vincula 10 itens DO EMPENHO)
        $nf = NotaFiscal::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $processo->id,
            'empenho_id' => $empenho->id,
            'contrato_id' => $contrato->id,
            'numero' => '1001',
            'serie' => '1',
            'tipo' => 'saida',
            'data_emissao' => now(),
            'valor' => 100.00, // 10 * 10
        ]);

        $vinculoNF = $this->service->store($processo, $item, [
            'processo_item_id' => $item->id,
            'nota_fiscal_id' => $nf->id,
            'empenho_id' => $empenho->id,
            'contrato_id' => $contrato->id,
            'quantidade' => 10,
            'valor_unitario' => 10.00,
            'valor_total' => 100.00,
        ], $this->empresa->id);

        $this->assertNotNull($vinculoNF);
        
        // 6. Teste de Validação: Tentativa de Criar NF excedendo Empenho
        // Empenho tem 20. Já usamos 10. Restam 10.
        // Tentar criar NF com 15.
        
        $nfExcesso = NotaFiscal::create([
            'empresa_id' => $this->empresa->id,
            'processo_id' => $processo->id,
            'empenho_id' => $empenho->id, // Same empenho
            'numero' => '1002',
            'tipo' => 'saida',
            'valor' => 150.00
        ]);

        try {
            $this->service->store($processo, $item, [
                'processo_item_id' => $item->id,
                'nota_fiscal_id' => $nfExcesso->id,
                'empenho_id' => $empenho->id,
                'quantidade' => 15, // Excede 10 disponíveis no empenho (20 total - 10 usada)
                'valor_unitario' => 10.00,
                'valor_total' => 150.00,
            ], $this->empresa->id);
            
            $this->fail("Deveria ter falhado por excesso de quantidade no Empenho");
        } catch (\Exception $e) {
            $this->assertStringContainsString('Quantidade solicitada', $e->getMessage());
        }
    }
}
