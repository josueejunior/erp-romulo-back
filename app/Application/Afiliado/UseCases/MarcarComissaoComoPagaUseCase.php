<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Models\AfiliadoComissaoRecorrente;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Use Case: Marcar Comissão como Paga
 * 
 * Marca uma comissão recorrente como paga
 */
final class MarcarComissaoComoPagaUseCase
{
    /**
     * Executa o use case
     */
    public function executar(
        int $comissaoId,
        ?string $dataPagamento = null,
        ?string $observacoes = null,
    ): AfiliadoComissaoRecorrente {
        return DB::transaction(function () use ($comissaoId, $dataPagamento, $observacoes) {
            $comissao = AfiliadoComissaoRecorrente::find($comissaoId);

            if (!$comissao) {
                throw new DomainException('Comissão não encontrada.');
            }

            if ($comissao->status === 'paga') {
                throw new DomainException('Esta comissão já foi marcada como paga.');
            }

            $comissao->update([
                'status' => 'paga',
                'data_pagamento_afiliado' => $dataPagamento ? Carbon::parse($dataPagamento) : Carbon::now(),
                'observacoes' => $observacoes ? ($comissao->observacoes ?? '') . "\n" . $observacoes : $comissao->observacoes,
            ]);

            Log::info('MarcarComissaoComoPagaUseCase - Comissão marcada como paga', [
                'comissao_id' => $comissaoId,
                'afiliado_id' => $comissao->afiliado_id,
                'valor_comissao' => $comissao->valor_comissao,
            ]);

            return $comissao->fresh();
        });
    }
}

