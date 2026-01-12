<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case para validar cupom de afiliado no checkout
 */
final class ValidarCupomAfiliadoUseCase
{
    /**
     * Valida um código de afiliado e retorna informações do desconto
     */
    public function executar(string $codigo): array
    {
        Log::debug('ValidarCupomAfiliadoUseCase::executar', ['codigo' => $codigo]);

        $codigo = strtoupper(trim($codigo));

        // Buscar afiliado pelo código
        $afiliado = Afiliado::where('codigo', $codigo)
            ->where('ativo', true)
            ->first();

        if (!$afiliado) {
            throw new DomainException('Código de afiliado inválido ou inativo.');
        }

        Log::info('ValidarCupomAfiliadoUseCase - Cupom válido', [
            'afiliado_id' => $afiliado->id,
            'codigo' => $afiliado->codigo,
            'desconto' => $afiliado->percentual_desconto,
        ]);

        return [
            'valido' => true,
            'afiliado_id' => $afiliado->id,
            'codigo' => $afiliado->codigo,
            'nome_afiliado' => $afiliado->nome,
            'percentual_desconto' => $afiliado->percentual_desconto,
            'percentual_comissao' => $afiliado->percentual_comissao,
        ];
    }

    /**
     * Calcula o valor com desconto
     */
    public function calcularDesconto(string $codigo, float $valorOriginal): array
    {
        $validacao = $this->executar($codigo);

        $desconto = ($valorOriginal * $validacao['percentual_desconto']) / 100;
        $valorFinal = $valorOriginal - $desconto;

        return [
            ...$validacao,
            'valor_original' => $valorOriginal,
            'valor_desconto' => round($desconto, 2),
            'valor_final' => round($valorFinal, 2),
        ];
    }
}







