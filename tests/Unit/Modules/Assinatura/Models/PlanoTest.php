<?php

namespace Tests\Unit\Modules\Assinatura\Models;

use App\Modules\Assinatura\Models\Plano;
use PHPUnit\Framework\TestCase;

class PlanoTest extends TestCase
{
    /**
     * Teste: Cálculo mensal deve aplicar 50% de desconto
     */
    public function test_calcular_preco_mensal_aplica_desconto_50_porcentro(): void
    {
        // Setup
        $plano = new Plano([
            'preco_mensal' => 100.00,
            'preco_anual' => 1000.00,
        ]);

        // Execução
        $valorCalculado = $plano->calcularPreco('mensal', 1);

        // Asserção: 100 * 0.5 = 50.00
        $this->assertEquals(50.00, $valorCalculado);
    }

    /**
     * Teste: Cálculo mensal para multiplos meses (sem ser anual)
     */
    public function test_calcular_preco_mensal_para_varios_meses(): void
    {
        // Setup
        $plano = new Plano([
            'preco_mensal' => 100.00,
        ]);

        // Execução (3 meses)
        $valorCalculado = $plano->calcularPreco('mensal', 3);

        // Asserção: (100 * 0.5) * 3 = 150.00
        $this->assertEquals(150.00, $valorCalculado);
    }

    /**
     * Teste: Cálculo anual usando preço anual definido
     */
    public function test_calcular_preco_anual_usa_preco_anual_definido(): void
    {
        // Setup
        $plano = new Plano([
            'preco_mensal' => 100.00,
            'preco_anual' => 900.00, // Preço anual específico
        ]);

        // Execução
        $valorCalculado = $plano->calcularPreco('anual');

        // Asserção: 900 * 0.5 = 450.00
        $this->assertEquals(450.00, $valorCalculado);
    }

    /**
     * Teste: Cálculo anual (12 meses) deve usar lógica anual
     */
    public function test_calcular_preco_por_12_meses_usa_logica_anual(): void
    {
        // Setup
        $plano = new Plano([
            'preco_mensal' => 100.00,
            'preco_anual' => 900.00,
        ]);

        // Execução (passando 12 meses, mesmo com flag mensal explicita ou implicita)
        // O método deve identificar 12 meses como anual
        $valorCalculado = $plano->calcularPreco('mensal', 12);

        // Asserção: 900 * 0.5 = 450.00
        $this->assertEquals(450.00, $valorCalculado);
    }

    /**
     * Teste: Fallback do cálculo anual (se não houver preço anual definido)
     * Regra: Mensal * 10
     */
    public function test_calcular_preco_anual_fallback_mensal_vezes_10(): void
    {
        // Setup
        $plano = new Plano([
            'preco_mensal' => 100.00,
            'preco_anual' => null, // Sem preço anual
        ]);

        // Execução
        $valorCalculado = $plano->calcularPreco('anual');

        // Asserção: (100 * 10) * 0.5 = 500.00
        $this->assertEquals(500.00, $valorCalculado);
    }

    /**
     * Teste: Arredondamento
     */
    public function test_arredondamento_casas_decimais(): void
    {
        // Setup: 138.57 mensais
        $plano = new Plano([
            'preco_mensal' => 138.57, // Valor quebrado
        ]);

        // Execução
        $valorCalculado = $plano->calcularPreco('mensal', 1);

        // Asserção: 138.57 * 0.5 = 69.285 -> Round(2) -> 69.29
        $this->assertEquals(69.29, $valorCalculado);
    }
}
