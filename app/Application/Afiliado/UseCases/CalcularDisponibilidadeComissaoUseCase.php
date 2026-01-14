<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Models\AfiliadoComissaoRecorrente;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Calcular Disponibilidade de Comissão
 * 
 * Atualiza status de comissões para "disponivel" após período de carência
 * 
 * Regra: Comissão só fica disponível 15 dias após pagamento confirmado
 */
final class CalcularDisponibilidadeComissaoUseCase
{
    /**
     * Calcula e atualiza disponibilidade de comissões
     * 
     * Deve ser executado diariamente via Command/Scheduler
     */
    public function executar(): int
    {
        Log::info('CalcularDisponibilidadeComissaoUseCase - Iniciando cálculo de disponibilidade');

        // Buscar comissões pendentes que já passaram do período de carência
        $comissoes = AfiliadoComissaoRecorrente::where('status', 'pendente')
            ->whereNotNull('data_pagamento_cliente')
            ->where('data_pagamento_cliente', '<=', Carbon::now()->subDays(15))
            ->whereNull('data_disponivel_em')
            ->get();

        $atualizadas = 0;

        foreach ($comissoes as $comissao) {
            $diasCarencia = $comissao->dias_carencia ?? 15;
            $dataDisponivel = Carbon::parse($comissao->data_pagamento_cliente)->addDays($diasCarencia);

            // Só atualizar se já passou o período de carência
            if ($dataDisponivel->isPast()) {
                $comissao->update([
                    'status' => 'disponivel',
                    'data_disponivel_em' => $dataDisponivel,
                ]);

                $atualizadas++;

                Log::debug('CalcularDisponibilidadeComissaoUseCase - Comissão disponibilizada', [
                    'comissao_id' => $comissao->id,
                    'afiliado_id' => $comissao->afiliado_id,
                    'valor_comissao' => $comissao->valor_comissao,
                    'data_pagamento_cliente' => $comissao->data_pagamento_cliente,
                    'data_disponivel_em' => $dataDisponivel,
                ]);
            }
        }

        Log::info('CalcularDisponibilidadeComissaoUseCase - Concluído', [
            'comissoes_atualizadas' => $atualizadas,
            'total_processadas' => $comissoes->count(),
        ]);

        return $atualizadas;
    }
}



