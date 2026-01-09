<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Models\AfiliadoPagamentoComissao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Listar Pagamentos de Comissões
 * 
 * Lista pagamentos de comissões com filtros
 */
final class ListarPagamentosComissaoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(
        ?int $afiliadoId = null,
        ?string $status = null,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        Log::debug('ListarPagamentosComissaoUseCase::executar', [
            'afiliado_id' => $afiliadoId,
            'status' => $status,
        ]);

        $query = AfiliadoPagamentoComissao::with('afiliado')
            ->orderBy('periodo_competencia', 'desc');

        if ($afiliadoId !== null) {
            $query->where('afiliado_id', $afiliadoId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}


