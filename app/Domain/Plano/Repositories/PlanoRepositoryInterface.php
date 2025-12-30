<?php

namespace App\Domain\Plano\Repositories;

use App\Domain\Plano\Entities\Plano;
use Illuminate\Support\Collection;

/**
 * Interface do Repository de Plano
 */
interface PlanoRepositoryInterface
{
    /**
     * Buscar plano por ID
     */
    public function buscarPorId(int $id): ?Plano;

    /**
     * Listar todos os planos ativos
     * 
     * @param array $filtros Filtros opcionais (ex: ['ativo' => true])
     * @return Collection<Plano>
     */
    public function listar(array $filtros = []): Collection;

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: relacionamentos)
     * 
     * @return \App\Modules\Assinatura\Models\Plano|null
     */
    public function buscarModeloPorId(int $id): ?\App\Modules\Assinatura\Models\Plano;
}

