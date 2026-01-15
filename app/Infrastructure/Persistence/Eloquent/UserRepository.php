<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
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
        // 🔥 CORREÇÃO: Normalizar email para lowercase
        $email = strtolower($email);
        
        // 🔥 SEGURANÇA: Verificar se já existe usuário com esse email no tenant
        // Pode acontecer de uma tentativa anterior ter criado o tenant e empresa mas falhado ao criar usuário
        // ou ter criado parcialmente um usuário que precisa ser atualizado
        // 🔥 CORREÇÃO: Usar LOWER() para comparação case-insensitive
        $existingUser = UserModel::withTrashed()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();
        
        if ($existingUser) {
            if ($existingUser->trashed()) {
                // Usuário existe mas está deletado (soft delete) - restaurar e atualizar
                \Log::info('UserRepository::criarAdministrador - Usuário existente encontrado (deletado), restaurando e atualizando', [
                    'user_id' => $existingUser->id,
                    'email' => $email,
                    'tenant_id' => $tenantId,
                ]);
                
                $existingUser->restore();
                $existingUser->update([
                    'name' => $nome,
                    'password' => Hash::make($senha),
                    'empresa_ativa_id' => $empresaId,
                    // excluido_em será limpo automaticamente pelo restore()
                ]);
                
                // Remover roles antigas e adicionar Administrador
                $existingUser->roles()->detach();
                $existingUser->assignRole('Administrador');
                
                // Atualizar relação com empresa
                $existingUser->empresas()->sync([$empresaId => ['perfil' => 'administrador']]);
                
                $model = $existingUser->fresh();
            } else {
                // Usuário existe e está ativo - isso não deveria acontecer se a validação funcionou
                // Mas pode acontecer em casos de race condition ou tentativas anteriores
                \Log::warning('UserRepository::criarAdministrador - Usuário já existe e está ativo', [
                    'user_id' => $existingUser->id,
                    'email' => $email,
                    'tenant_id' => $tenantId,
                ]);
                
                throw new DomainException("Um usuário com o email {$email} já existe neste tenant.");
            }
        } else {
            // Criar novo usuário normalmente
            $model = UserModel::create([
                'name' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'empresa_ativa_id' => $empresaId,
            ]);

            $model->assignRole('Administrador');
            $model->empresas()->attach($empresaId, ['perfil' => 'administrador']);
        }

        return $this->toDomain($model->fresh(), $tenantId);
    }

    /**
     * Criar usuário comum
     * Apenas persiste o User, sem atribuir roles ou vincular empresas
     * Responsabilidades de negócio (roles, empresas) devem ser feitas no UseCase
     */
    public function criar(User $user): User
    {
        // Normalizar email para lowercase antes de inserir
        // Isso garante consistência e evita problemas de case sensitivity
        $userData = $this->toArray($user);
        $userData['email'] = strtolower($userData['email']);
        
        $model = UserModel::create($userData);
        
        return $this->toDomain($model->fresh(), $user->tenantId);
    }

    /**
     * Vincular usuário a uma empresa com perfil específico
     * Método de infraestrutura para persistir relacionamento many-to-many
     * Se já existir vínculo, atualiza o perfil
     */
    public function vincularUsuarioEmpresa(int $userId, int $empresaId, string $perfil): void
    {
        $model = UserModel::findOrFail($userId);
        
        // Verificar se já existe vínculo
        $existeVinculo = $model->empresas()->where('empresas.id', $empresaId)->exists();
        
        if ($existeVinculo) {
            // Atualizar perfil existente
            $model->empresas()->updateExistingPivot($empresaId, ['perfil' => strtolower($perfil)]);
        } else {
            // Criar novo vínculo
            $model->empresas()->attach($empresaId, ['perfil' => strtolower($perfil)]);
        }
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

        // 🔥 CORREÇÃO: Usar LOWER() para comparação case-insensitive (PostgreSQL é case-sensitive)
        // Isso garante que emails como "Email@Example.com" e "email@example.com" sejam tratados como iguais
        $emailLower = strtolower($email);
        
        // 🔥 CORREÇÃO CRÍTICA: A constraint unique do PostgreSQL NÃO respeita soft deletes
        // Se existe um usuário deletado com esse email, a constraint bloqueia a inserção
        // Precisamos verificar INCLUINDO usuários deletados para detectar esse caso
        $query = UserModel::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$emailLower]);
        
        if ($excluirUserId) {
            $query->where('id', '!=', $excluirUserId);
        }

        // Verificar se existe algum usuário (ativo ou deletado)
        $userFound = $query->first();
        
        if ($userFound) {
            // Se o usuário está deletado (soft delete), permitir criação (email disponível)
            if ($userFound->trashed()) {
                \Log::warning('UserRepository::emailExiste - Email encontrado mas usuário está deletado (soft delete). Constraint unique do PostgreSQL ainda bloqueia criação!', [
                    'email' => $email,
                    'email_lower' => $emailLower,
                    'user_id' => $userFound->id,
                    'user_email' => $userFound->email,
                    'is_trashed' => true,
                    'tenant_id' => $tenantId,
                    'deleted_at' => $userFound->getAttribute($userFound->getDeletedAtColumn()),
                ]);
                // ⚠️ PROBLEMA: Retornar false aqui permite que o UseCase tente criar,
                // mas a constraint unique do banco vai bloquear
                // SOLUÇÃO IDEAL: Usar unique index parcial (WHERE deleted_at IS NULL) no banco
                // SOLUÇÃO TEMPORÁRIA: Retornar true para evitar erro de constraint
                // Mas isso impede criar usuário com email de usuário deletado
                return true; // ⚠️ Impede criação se houver usuário deletado
            }
            
            // Usuário ativo encontrado
            \Log::warning('UserRepository::emailExiste - Email encontrado (usuário ativo)', [
                'email' => $email,
                'email_lower' => $emailLower,
                'user_id' => $userFound->id ?? null,
                'user_email' => $userFound->email ?? null,
                'user_name' => $userFound->name ?? null,
                'tenant_id' => $tenantId,
                'excluir_user_id' => $excluirUserId,
                'current_database' => $currentDatabase,
            ]);
            return true;
        } else {
            \Log::debug('UserRepository::emailExiste - Email não encontrado', [
                'email' => $email,
                'email_lower' => $emailLower,
                'tenant_id' => $tenantId,
                'current_database' => $currentDatabase,
            ]);
            return false;
        }
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

