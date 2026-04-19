<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pncp;

use App\Services\Pncp\PncpCompraParaProcessoMapper;
use PHPUnit\Framework\TestCase;

final class PncpCompraParaProcessoMapperFormacaoTest extends TestCase
{
    public function test_encontra_item_por_numero(): void
    {
        $rows = [
            ['numeroItem' => 2, 'valorUnitarioEstimado' => 5],
            ['numeroItem' => 3, 'valorUnitarioEstimado' => 99.5],
        ];
        $hit = PncpCompraParaProcessoMapper::encontrarItemPncpPorNumero($rows, 3);
        $this->assertNotNull($hit);
        $this->assertSame(99.5, $hit['valorUnitarioEstimado']);
    }

    public function test_mapear_referencia_deriva_unitario_a_partir_de_total_e_quantidade(): void
    {
        $ref = PncpCompraParaProcessoMapper::mapearReferenciaFormacaoPreco([
            'numeroItem' => 1,
            'quantidade' => 4,
            'valorTotal' => 100,
            'descricao' => 'X',
            'unidadeMedida' => 'UN',
        ]);
        $this->assertSame(1, $ref['numero_item']);
        $this->assertSame(25.0, $ref['valor_unitario_estimado']);
        $this->assertSame(100.0, $ref['valor_total']);
    }

    public function test_listar_numeros(): void
    {
        $nums = PncpCompraParaProcessoMapper::listarNumerosItensPncp([
            ['numeroItem' => 3],
            ['numeroItem' => 1],
            ['numeroItem' => 3],
        ]);
        $this->assertSame([1, 3], $nums);
    }
}
