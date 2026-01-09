<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Models\AfiliadoComissaoRecorrente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Listar Comissões (Admin)
 * 
 * Lista comissões com filtros para o painel administrativo
 */
final class ListarComissoesAdminUseCase
{
    /**
     * Executa o use case
     */
    public function executar(
        ?int $afiliadoId = null,
        ?string $status = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        Log::debug('ListarComissoesAdminUseCase::executar', [
            'afiliado_id' => $afiliadoId,
            'status' => $status,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ]);

        $query = AfiliadoComissaoRecorrente::with(['afiliado', 'indicacao'])
            ->orderBy('data_inicio_ciclo', 'desc');

        // Filtros
        if ($afiliadoId !== null) {
            $query->where('afiliado_id', $afiliadoId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($dataInicio !== null) {
            $query->where('data_inicio_ciclo', '>=', $dataInicio);
        }

        if ($dataFim !== null) {
            $query->where('data_inicio_ciclo', '<=', $dataFim);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}

