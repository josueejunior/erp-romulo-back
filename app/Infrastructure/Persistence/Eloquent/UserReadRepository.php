<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read Repository: Apenas para leitura e apresentação
 * Conhece Eloquent, mas controller não conhece
 */
class UserReadRepository implements UserReadRepositoryInterface
{
    public function buscarComRelacionamentos(int $userId): ?array
    {
        $user = UserModel::with(['empresas', 'roles'])->find($userId);
        
        if (!$user) {
            return null;
        }

        // Garantir que empresas seja sempre um array
        $empresas = $user->empresas->map(fn($e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
        ])->toArray();

        // Garantir que roles seja sempre um array
        $roles = $user->roles->pluck('name')->toArray();

        // Buscar empresa ativa se existir
        $empresaAtiva = null;
        if ($user->empresa_ativa_id) {
            $empresaAtivaModel = $user->empresas->firstWhere('id', $user->empresa_ativa_id);
            if ($empresaAtivaModel) {
                $empresaAtiva = [
                    'id' => $empresaAtivaModel->id,
                    'razao_social' => $empresaAtivaModel->razao_social,
                ];
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'empresa_ativa_id' => $user->empresa_ativa_id,
            'empresa_ativa' => $empresaAtiva,
            'roles' => $roles,
            'roles_list' => $roles, // Frontend espera isso também
            'empresas' => $empresas, // Garantir que seja array
            'empresas_list' => $empresas, // Frontend espera isso também
        ];
    }

    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator
    {
        $query = UserModel::with(['empresas']);

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('name')->paginate($perPage);

        // Transformar Collection para array
        $items = $paginator->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_ativa_id' => $user->empresa_ativa_id,
            ];
        })->values()->toArray();

        // Criar novo paginator com array (não Collection)
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => $paginator->path(),
                'pageName' => $paginator->getPageName(),
            ]
        );
    }
}

