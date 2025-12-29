<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Implementação do Repository de User usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(UserModel $model, int $tenantId): User
    {
        return new User(
            id: $model->id,
            tenantId: $tenantId,
            nome: $model->name,
            email: $model->email,
            senhaHash: $model->password,
            empresaAtivaId: $model->empresa_ativa_id,
        );
    }

    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(User $user): array
    {
        return [
            'name' => $user->nome,
            'email' => $user->email,
            'password' => $user->senhaHash,
            'empresa_ativa_id' => $user->empresaAtivaId,
        ];
    }

    public function criarAdministrador(
        int $tenantId,
        int $empresaId,
        string $nome,
        string $email,
        string $senha
    ): User {
        $model = UserModel::create([
            'name' => $nome,
            'email' => $email,
            'password' => Hash::make($senha),
            'empresa_ativa_id' => $empresaId,
        ]);

        $model->assignRole('Administrador');
        $model->empresas()->attach($empresaId, ['perfil' => 'administrador']);

        return $this->toDomain($model->fresh(), $tenantId);
    }

    public function criar(User $user, int $empresaId, string $role): User
    {
        $model = UserModel::create($this->toArray($user));

        $model->assignRole($role);
        $model->empresas()->attach($empresaId, ['perfil' => strtolower($role)]);

        return $this->toDomain($model->fresh(), $user->tenantId);
    }

    public function buscarPorId(int $id): ?User
    {
        $model = UserModel::find($id);
        if (!$model) {
            return null;
        }

        // Obter tenantId do contexto atual
        $tenantId = tenancy()->tenant?->id ?? 0;
        return $this->toDomain($model, $tenantId);
    }

    public function buscarPorEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();
        if (!$model) {
            return null;
        }

        $tenantId = tenancy()->tenant?->id ?? 0;
        return $this->toDomain($model, $tenantId);
    }

    public function emailExiste(string $email, ?int $excluirUserId = null): bool
    {
        $query = UserModel::where('email', $email);
        
        if ($excluirUserId) {
            $query->where('id', '!=', $excluirUserId);
        }

        return $query->exists();
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = UserModel::query();

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('name')->paginate($perPage);

        $tenantId = tenancy()->tenant?->id ?? 0;
        $paginator->getCollection()->transform(function ($model) use ($tenantId) {
            return $this->toDomain($model, $tenantId);
        });

        return $paginator;
    }

    public function atualizar(User $user): User
    {
        $model = UserModel::findOrFail($user->id);
        $model->update($this->toArray($user));
        return $this->toDomain($model->fresh(), $user->tenantId);
    }

    public function deletar(int $id): void
    {
        UserModel::findOrFail($id)->delete();
    }

    public function reativar(int $id): void
    {
        UserModel::withTrashed()->findOrFail($id)->restore();
    }

    /**
     * Atualizar role do usuário (método auxiliar)
     */
    public function atualizarRole(int $userId, string $role): void
    {
        $model = UserModel::findOrFail($userId);
        $model->syncRoles([$role]);
    }
}

