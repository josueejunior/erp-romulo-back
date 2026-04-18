<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Listar Planos
 * Orquestra a listagem de planos com filtros
 */
class ListarPlanosUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna collection com entidades de domínio
     * 
     * @param array $filtros Filtros opcionais
     * @return Collection<Plano>
     */
    public function executar(array $filtros = []): Collection
    {
        Log::info('🔍 ListarPlanosUseCase::executar - Iniciando', [
            'filtros' => $filtros,
        ]);

        Log::info('🔍 ListarPlanosUseCase::executar - Chamando PlanoRepository::listar');
        $planos = $this->planoRepository->listar($filtros);

        Log::info('✅ ListarPlanosUseCase::executar - Repository retornou planos', [
            'count' => $planos->count(),
            'ids' => $planos->pluck('id')->toArray(),
        ]);

        return $planos;
    }
}



