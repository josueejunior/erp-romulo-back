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
        
        // 🔥 CORREÇÃO: Garantir que o modelo está acessível dentro da transação
        // Usar refresh() e setConnection para garantir visibilidade na transação atual
        $model->refresh();
        
        // Forçar conexão atual para garantir que está na mesma transação
        $model->setConnection($model->getConnectionName());
        
        return $this->toDomain($model, $user->tenantId);
    }
    
    /**
     * Buscar modelo Eloquent por ID (método auxiliar para UserRoleService)
     * Usa withoutGlobalScopes para garantir busca dentro de transações
     */
    public function buscarModeloEloquentPorId(int $id): ?UserModel
    {
        // Sem global scopes para garantir busca direta dentro de transações
        return UserModel::withoutGlobalScopes()->find($id);
    }

    /**
     * Vincular usuário a uma empresa com perfil específico
     * Método de infraestrutura para persistir relacionamento many-to-many
     * Se já existir vínculo, atualiza o perfil
     * 
     * 🔥 CORREÇÃO: Usa newFromBuilder para evitar Global Scope que filtra usuários sem empresas
     */
    public function vincularUsuarioEmpresa(int $userId, int $empresaId, string $perfil): void
    {
        // 🔥 CORREÇÃO: Buscar dados do banco diretamente para evitar Global Scope
        // O Global Scope filtra usuários sem empresas, mas estamos criando o vínculo agora
        $userData = \DB::table('users')->where('id', $userId)->first();
        
        if (!$userData) {
            throw new \RuntimeException("Usuário com ID {$userId} não encontrado para vincular empresa.");
        }
        
        // Criar modelo a partir dos dados do banco (bypassa Global Scope)
        $model = (new UserModel())->newFromBuilder($userData);
        
        // Verificar se já existe vínculo usando DB direto (mais confiável dentro de transação)
        $existeVinculo = \DB::table('empresa_user')
            ->where('user_id', $userId)
            ->where('empresa_id', $empresaId)
            ->exists();
        
        if ($existeVinculo) {
            // Atualizar perfil existente
            $model->empresas()->updateExistingPivot($empresaId, ['perfil' => strtolower($perfil)]);
        } else {
            // Criar novo vínculo
            $model->empresas()->attach($empresaId, ['perfil' => strtolower($perfil)]);
        }
    }

    /**
     * Buscar usuário por ID
     * 
     * 🔥 SIMPLIFICADO: Global Scope cuida do isolamento automaticamente
     */
    public function buscarPorId(int $id): ?User
    {
        // Global Scope aplica filtro de tenant automaticamente
        $model = UserModel::withTrashed()->find($id);
        if (!$model) {
            return null;
        }

        // Obter tenantId do contexto (já filtrado pelo scope)
        $tenantId = tenancy()->tenant?->id ?? 0;
        return $this->toDomain($model, $tenantId);
    }

    /**
     * Buscar usuário por email
     * 
     * 🔥 SIMPLIFICADO: Global Scope cuida do isolamento automaticamente
     */
    public function buscarPorEmail(string $email): ?User
    {
        // Global Scope aplica filtro de tenant automaticamente
        // Email já é normalizado no modelo (lowercase)
        $model = UserModel::where('email', strtolower($email))->first();
        if (!$model) {
            return null;
        }

        $tenantId = tenancy()->tenant?->id ?? 0;
        return $this->toDomain($model, $tenantId);
    }

    /**
     * Verificar se email existe
     * 
     * 🔥 SIMPLIFICADO: Global Scope cuida do isolamento de tenant automaticamente
     * Não precisa validar tenant_id manualmente - o banco trabalha para você
     */
    public function emailExiste(string $email, ?int $excluirUserId = null): bool
    {
        // Normalizar email para lowercase
        $emailLower = strtolower($email);
        
        // ⚠️ CRÍTICO: Remover Global Scope temporariamente para verificação de email
        // O Global Scope pode filtrar usuários que ainda não foram vinculados a empresas,
        // mas precisamos verificar se o email existe independentemente do relacionamento.
        // Como estamos dentro do contexto do tenant (banco tenant_*), não precisamos do filtro adicional.
        $query = UserModel::withoutGlobalScopes()
            ->withTrashed()
            ->whereRaw('LOWER(email) = ?', [$emailLower]);
        
        if ($excluirUserId) {
            $query->where('id', '!=', $excluirUserId);
        }

        $userFound = $query->first();
        
        if (!$userFound) {
            return false;
        }

        // Se usuário está deletado (soft delete), ainda bloqueia por constraint unique
        // Retornar true para evitar erro de constraint no banco
        if ($userFound->trashed()) {
            \Log::warning('UserRepository::emailExiste - Email encontrado mas usuário deletado', [
                'email' => $email,
                'user_id' => $userFound->id,
            ]);
            return true; // Bloqueia criação (constraint unique do PostgreSQL)
        }
        
        return true; // Email existe e usuário está ativo
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

