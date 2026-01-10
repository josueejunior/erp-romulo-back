<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
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
        \Log::info('UserRepository::criar iniciado', [
            'email' => $user->email,
            'empresa_id' => $empresaId,
            'role' => $role,
        ]);

        $model = UserModel::create($this->toArray($user));

        \Log::info('UserRepository::criar - Model criado', [
            'user_id' => $model->id,
            'email' => $model->email,
        ]);

        $model->assignRole($role);
        $model->empresas()->attach($empresaId, ['perfil' => strtolower($role)]);

        $userDomain = $this->toDomain($model->fresh(), $user->tenantId);

        \Log::info('UserRepository::criar concluído', [
            'user_id' => $userDomain->id,
            'email' => $userDomain->email,
        ]);

        return $userDomain;
    }

    public function buscarPorId(int $id): ?User
    {
        // 🔥 CORREÇÃO: Usar withTrashed para buscar também usuários inativos (soft deleted)
        // Isso é necessário para operações de reativação e para evitar erros "Usuário não encontrado"
        $model = UserModel::withTrashed()->find($id);
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
        // Log detalhado para debug
        $tenantId = tenancy()->tenant?->id ?? null;
        $tenantInitialized = tenancy()->initialized ?? false;
        $currentDatabase = tenancy()->initialized ? \DB::connection()->getDatabaseName() : 'central';
        
        \Log::debug('UserRepository::emailExiste - Verificando email', [
            'email' => $email,
            'excluir_user_id' => $excluirUserId,
            'tenant_id' => $tenantId,
            'tenancy_initialized' => $tenantInitialized,
            'current_database' => $currentDatabase,
        ]);

        // 🔥 CORREÇÃO: Eloquent com SoftDeletes já exclui automaticamente registros deletados
        // Mas vamos garantir explicitamente que não estamos incluindo deletados
        // UserModel usa SoftDeletes, então where() já exclui automaticamente deletados
        $query = UserModel::where('email', $email);
        
        if ($excluirUserId) {
            $query->where('id', '!=', $excluirUserId);
        }

        // Verificar se existe usuário ativo (não deletado)
        // exists() já exclui soft deletes automaticamente
        $exists = $query->exists();
        
        // Se encontrou, buscar detalhes para log e validação
        if ($exists) {
            $userFound = $query->first();
            
            // Verificar explicitamente se está deletado (por segurança)
            // Usar trashed() é mais seguro pois funciona independente do nome da coluna (deleted_at vs excluido_em)
            if ($userFound && $userFound->trashed()) {
                \Log::warning('UserRepository::emailExiste - Email encontrado mas usuário está deletado (soft delete), ignorando', [
                    'email' => $email,
                    'user_id' => $userFound->id,
                    'is_trashed' => true,
                    'tenant_id' => $tenantId,
                ]);
                return false; // Usuário deletado não conta como existente
            }
            
            \Log::warning('UserRepository::emailExiste - Email encontrado (usuário ativo)', [
                'email' => $email,
                'user_id' => $userFound->id ?? null,
                'user_name' => $userFound->name ?? null,
                'tenant_id' => $tenantId,
                'excluir_user_id' => $excluirUserId,
                'current_database' => $currentDatabase,
            ]);
        } else {
            \Log::debug('UserRepository::emailExiste - Email não encontrado', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'current_database' => $currentDatabase,
            ]);
        }

        return $exists;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = UserModel::query();

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
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

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: controllers que precisam de relacionamentos)
     */
    public function buscarModeloPorId(int $id): ?UserModel
    {
        return UserModel::with(['empresas', 'roles'])->find($id);
    }

    public function atualizar(User $user): User
    {
        $model = UserModel::findOrFail($user->id);
        $model->update($this->toArray($user));
        return $this->toDomain($model->fresh(), $user->tenantId);
    }

    public function deletar(int $id): void
    {
        // 🔥 CORREÇÃO: Usar withTrashed para encontrar usuário mesmo se já estiver inativo
        // Isso evita o erro "Usuário não encontrado" quando tenta inativar novamente
        $user = UserModel::withTrashed()->findOrFail($id);
        
        // Se já está deletado (soft delete), não faz nada
        if ($user->trashed()) {
            return;
        }
        
        $user->delete();
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

    /**
     * Sincronizar empresas do usuário
     */
    public function sincronizarEmpresas(int $userId, array $empresasIds): void
    {
        \Log::info('UserRepository: Sincronizando empresas', [
            'user_id' => $userId,
            'empresas_ids' => $empresasIds,
            'empresas_count' => count($empresasIds),
        ]);
        
        $model = UserModel::findOrFail($userId);
        
        // Verificar empresas atuais antes da sincronização
        $empresasAntigas = $model->empresas->pluck('id')->toArray();
        \Log::info('UserRepository: Empresas antes da sincronização', [
            'user_id' => $userId,
            'empresas_antigas' => $empresasAntigas,
        ]);
        
        // Sincronizar (mesmo com 1 empresa, deve funcionar)
        $model->empresas()->sync($empresasIds);
        
        // Verificar empresas após sincronização
        $model->refresh();
        $empresasNovas = $model->empresas->pluck('id')->toArray();
        \Log::info('UserRepository: Empresas após sincronização', [
            'user_id' => $userId,
            'empresas_novas' => $empresasNovas,
            'sincronizacao_ok' => $empresasNovas === $empresasIds,
        ]);
    }

    public function buscarEmpresaAtiva(int $userId): ?\App\Domain\Empresa\Entities\Empresa
    {
        $model = UserModel::findOrFail($userId);
        
        if (!$model->empresa_ativa_id) {
            return null;
        }

        $empresaRepository = app(EmpresaRepositoryInterface::class);
        return $empresaRepository->buscarPorId($model->empresa_ativa_id);
    }

    public function buscarEmpresas(int $userId): array
    {
        $model = UserModel::findOrFail($userId);
        $empresas = $model->empresas;
        
        $empresaRepository = app(EmpresaRepositoryInterface::class);
        $result = [];
        
        foreach ($empresas as $empresaModel) {
            $empresa = $empresaRepository->buscarPorId($empresaModel->id);
            if ($empresa) {
                $result[] = $empresa;
            }
        }
        
        return $result;
    }

    public function atualizarEmpresaAtiva(int $userId, int $empresaId): User
    {
        $model = UserModel::findOrFail($userId);
        
        // Validar se o usuário tem acesso a esta empresa (regra de negócio no Repository)
        $empresas = $this->buscarEmpresas($userId);
        $temAcesso = collect($empresas)->contains(function ($empresa) use ($empresaId) {
            return $empresa->id === $empresaId;
        });
        
        if (!$temAcesso) {
            throw new \App\Domain\Exceptions\DomainException('Você não tem acesso a esta empresa.');
        }
        
        $model->empresa_ativa_id = $empresaId;
        $model->save();
        
        $tenantId = tenancy()->tenant?->id ?? 0;
        return $this->toDomain($model->fresh(), $tenantId);
    }
}

