<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para listar Afiliados
 */
final class ListarAfiliadosUseCase
{
    /**
     * Executa o use case
     */
    public function executar(
        ?string $search = null,
        ?bool $ativo = null,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        Log::debug('ListarAfiliadosUseCase::executar', [
            'search' => $search,
            'ativo' => $ativo,
            'perPage' => $perPage,
            'page' => $page,
        ]);

        $query = Afiliado::query()
            ->withCount([
                'indicacoes',
                'indicacoesAtivas',
                'indicacoesInadimplentes',
                'indicacoesCanceladas',
            ])
            ->buscar($search);

        // Filtro por status ativo
        if ($ativo !== null) {
            $query->where('ativo', $ativo);
        }

        // Ordenação padrão
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}

