<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Services\ProcessoItemVinculoService;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Mockery;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscalFlowTest extends TestCase
{
    private $service;
    private $notaFiscalRepoMock;
    private $contratoRepo; // Mocked but unused in this specific test
    private $afRepo;       // Mocked but unused in this specific test
    private $empenhoRepo;  // Mocked but unused in this specific test

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->contratoRepo = Mockery::mock(ContratoRepositoryInterface::class);
        $this->afRepo = Mockery::mock(AutorizacaoFornecimentoRepositoryInterface::class);
        $this->empenhoRepo = Mockery::mock(EmpenhoRepositoryInterface::class);
        $this->notaFiscalRepoMock = Mockery::mock(NotaFiscalRepositoryInterface::class);

        $this->service = new ProcessoItemVinculoService(
            $this->contratoRepo,
            $this->afRepo,
            $this->empenhoRepo,
            $this->notaFiscalRepoMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_quantidade_ignores_limit_for_entrada_nf()
    {
        // 1. Mock Item
        $item = Mockery::mock(ProcessoItem::class)->makePartial();
        $item->quantidade = 10;
        $item->id = 1;

        // Note: The service methods call $item->vinculos()... 
        // Since we are testing the "Early Return" path for Entrance NF, 
        // the code should return BEFORE accessing $item->vinculos().
        // So we don't even need to mock vinculos() if logic works!
        
        // 2. Mock Nota Fiscal Repository to return 'Entrada'
        $nfId = 123;
        $nfMock = new \App\Domain\NotaFiscal\Entities\NotaFiscal(
            id: $nfId,
            empresaId: 1,
            processoId: null,
            tipo: 'entrada'
        );
        
        $this->notaFiscalRepoMock
            ->shouldReceive('buscarPorId')
            ->with($nfId)
            ->once()
            ->andReturn($nfMock);

        // 3. Call validateQuantidade
        // We request 100 quantity (>> item->quantidade 10).
        // Should NOT throw exception.
        try {
            $this->service->validateQuantidade($item, 100, ['nota_fiscal_id' => $nfId]);
            $this->assertTrue(true, "Validation passed for Entrance NF with excess quantity.");
        } catch (\Exception $e) {
            $this->fail("Validation failed for Entrance NF: " . $e->getMessage());
        }
    }

    public function test_validate_quantidade_enforces_limit_for_saida_nf()
    {
        // 1. Mock Item and Relationship Chain
        $item = Mockery::mock(ProcessoItem::class)->makePartial();
        $item->quantidade = 10;
        $item->id = 1;

        // Mock the Query Builder chain for vinculos()
        $queryMock = Mockery::mock(HasMany::class);
        $queryMock->shouldReceive('whereNotNull')->andReturnSelf();
        $queryMock->shouldReceive('when')->andReturnSelf();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('sum')->andReturn(5); // 5 already used

        $item->shouldReceive('vinculos')->andReturn($queryMock);

        // 2. Mock Nota Fiscal Repository to return 'Saida'
        $nfId = 456;
        $nfMock = new \App\Domain\NotaFiscal\Entities\NotaFiscal(
            id: $nfId,
            empresaId: 1,
            processoId: null,
            tipo: 'saida'
        );
        
        $this->notaFiscalRepoMock
            ->shouldReceive('buscarPorId')
            ->with($nfId)
            ->once()
            ->andReturn($nfMock);

        // 3. Call validateQuantidade
        // Item Qtd: 10. Used: 5. Available: 5.
        // We request: 6.
        // Should THROW exception.
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Quantidade solicitada (6) excede a quantidade disponível");

        $this->service->validateQuantidade($item, 6, ['nota_fiscal_id' => $nfId]);
    }

    public function test_validate_quantidade_allows_limit_for_saida_nf_if_sufficient()
    {
        // 1. Mock Item and Relationship Chain
        $item = Mockery::mock(ProcessoItem::class)->makePartial();
        $item->quantidade = 10;
        $item->id = 1;

        // Mock the Query Builder chain for vinculos()
        $queryMock = Mockery::mock(HasMany::class);
        $queryMock->shouldReceive('whereNotNull')->andReturnSelf();
        $queryMock->shouldReceive('when')->andReturnSelf();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('sum')->andReturn(5); // 5 already used

        $item->shouldReceive('vinculos')->andReturn($queryMock);

        // 2. Mock Nota Fiscal Repository to return 'Saida'
        $nfId = 789;
        $nfMock = new \App\Domain\NotaFiscal\Entities\NotaFiscal(
            id: $nfId,
            empresaId: 1,
            processoId: null,
            tipo: 'saida'
        );
        
        $this->notaFiscalRepoMock
            ->shouldReceive('buscarPorId')
            ->with($nfId)
            ->once()
            ->andReturn($nfMock);

        // 3. Call validateQuantidade
        // Item Qtd: 10. Used: 5. Available: 5.
        // We request: 5.
        // Should PASS.
        
        try {
            $this->service->validateQuantidade($item, 5, ['nota_fiscal_id' => $nfId]);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("Validation should pass for sufficient quantity: " . $e->getMessage());
        }
    }
}
