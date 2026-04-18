<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\Processo\Services\ProcessoItemVinculoService;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Models\Processo;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Mockery;

use App\Domain\NotaFiscal\Entities\NotaFiscal;

class ProcessoItemVinculoServiceTest extends TestCase
{
    private $service;
    private $contratoRepo;
    private $afRepo;
    private $empenhoRepo;
    private $nfRepo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock DB facade to prevent real DB queries in fallback
        \Illuminate\Support\Facades\DB::shouldReceive('table')->andReturnSelf();
        \Illuminate\Support\Facades\DB::shouldReceive('where')->andReturnSelf();
        \Illuminate\Support\Facades\DB::shouldReceive('value')->andReturnNull();

        $this->contratoRepo = Mockery::mock(ContratoRepositoryInterface::class);
        $this->afRepo = Mockery::mock(AutorizacaoFornecimentoRepositoryInterface::class);
        $this->empenhoRepo = Mockery::mock(EmpenhoRepositoryInterface::class);
        $this->nfRepo = Mockery::mock(NotaFiscalRepositoryInterface::class);

        $this->service = new ProcessoItemVinculoService(
            $this->contratoRepo,
            $this->afRepo,
            $this->empenhoRepo,
            $this->nfRepo
        );
    }

    public function testEntradaNoteIgnoresQuantityLimit()
    {
        // Use real instance for data holding
        $item = new ProcessoItem();
        $item->quantidade = 10;
        
        $data = [
            'nota_fiscal_id' => 1,
            'quantidade' => 5,
            'ignore_quantity_check' => true // A flag que o Controller envia
        ];

        // Se a flag funciona, método retorna void sem exception.
        $this->service->validateQuantidade($item, 5, $data);
        
        $this->assertTrue(true, 'Validação passou com flag ignore_quantity_check');
    }

    public function testEntradaNoteByTypeIgnoresLimit()
    {
        $item = new ProcessoItem();
        $data = ['nota_fiscal_id' => 99, 'quantidade' => 1000];

        // Return a valid Domain Entity
        $mockNf = new NotaFiscal(
            id: 99,
            empresaId: 1,
            processoId: 1,
            tipo: 'entrada'
        );
        $this->nfRepo->shouldReceive('buscarPorId')->with(99)->andReturn($mockNf);

        $this->service->validateQuantidade($item, 1000, $data);
        $this->assertTrue(true, 'Validação passou pelo tipo entrada no repository');
    }
}
