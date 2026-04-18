<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Support\Collection;
use App\Modules\Assinatura\Models\Plano as PlanoModel;

/**
 * 🔥 DDD: UseCase para listar planos no admin
 * Retorna dados formatados para apresentação (evita N+1)
 */
class ListarPlanosAdminUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Lista planos formatados para admin
     * 
     * @param array $filtros Filtros opcionais
     * @return Collection Array de arrays (dados formatados)
     */
    public function executar(array $filtros = []): Collection
    {
        $planosDomain = $this->planoRepository->listar($filtros);

        // Buscar modelos Eloquent em uma única query (evita N+1)
        $ids = $planosDomain->pluck('id')->toArray();
        
        if (empty($ids)) {
            return collect([]);
        }

        // Buscar todos os modelos de uma vez usando whereIn (evita N+1)
        $modelos = PlanoModel::whereIn('id', $ids)->get()->keyBy('id');

        // Transformar para array formatado
        return $planosDomain->map(function ($planoDomain) use ($modelos) {
            $modelo = $modelos->get($planoDomain->id);
            
            if (!$modelo) {
                return null;
            }

            return $modelo->toArray();
        })->filter()->values();
    }
}

