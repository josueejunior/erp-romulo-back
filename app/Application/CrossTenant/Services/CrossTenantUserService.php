<?php

declare(strict_types=1);

namespace App\Application\CrossTenant\Services;

use App\Application\CadastroPublico\Services\UsersLookupService;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Services\AdminTenancyRunner;
use App\Models\Tenant;
use App\Models\TenantEmpresa;
use App\Modules\Auth\Models\User;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service para gerenciar usuários cross-tenant
 * 
 * Permite adicionar um usuário existente a um novo tenant,
 * ou criar um novo usuário em múltiplos tenants.
 * 
 * Fluxos suportados:
 * 1. Admin vincula usuário existente a outro tenant
 * 2. Dono do tenant convida usuário por email
 * 3. Auto-registro quando CNPJ já existe em outro tenant
 */
final class CrossTenantUserService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
        private readonly UsersLookupService $usersLookupService,
    ) {}

    /**
     * Vincular usuário existente a um novo tenant
     * 
     * @param string $email Email do usuário a vincular
     * @param int $targetTenantId ID do tenant de destino
     * @param int|null $targetEmpresaId ID da empresa dentro do tenant (null = primeira disponível)
     * @param string $role Role do usuário no novo tenant ('admin', 'operador', 'consulta')
     * @param string|null $password Senha para o novo usuário (null = gerar aleatória)
     * @return array Dados do vínculo criado
     * @throws \Exception
     */
    public function vincularUsuarioATenant(
        string $email,
        int $targetTenantId,
        ?int $targetEmpresaId = null,
        string $role = 'operador',
        ?string $password = null,
    ): array {
        Log::info('CrossTenantUserService::vincularUsuarioATenant - Iniciando', [
            'email' => $email,
            'target_tenant_id' => $targetTenantId,
            'target_empresa_id' => $targetEmpresaId,
            'role_input' => $role,
        ]);

        // Mapear role para o nome correto no sistema (Case-insensitive)
        $roleInput = strtolower(trim($role));
        $roleMap = [
            'admin' => 'Administrador',
            'administrador' => 'Administrador',
            'operador' => 'Operacional',
            'operacional' => 'Operacional',
            'consulta' => 'Consulta',
        ];

        $roleMapped = $roleMap[$roleInput] ?? 'Operacional'; // Default para Operacional se não encontrar

        // 1. Buscar o tenant de destino
        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant) {
            throw new \Exception("Tenant {$targetTenantId} não encontrado.");
        }

        $targetTenantDomain = $this->tenantRepository->buscarPorId($targetTenantId);
        if (!$targetTenantDomain) {
            throw new \Exception("Tenant domain {$targetTenantId} não encontrado.");
        }

        // 2. Buscar dados do usuário em qualquer tenant onde ele já exista
        $sourceUserData = $this->buscarUsuarioEmQualquerTenant($email);
        
        // 3. Verificar se o usuário já existe no tenant de destino
        $userId = $this->adminTenancyRunner->runForTenant($targetTenantDomain, function () use (
            $email, $targetEmpresaId, $sourceUserData, $role, $roleMapped, $password, $targetTenantId
        ) {
            // Verificar se já existe
            $existingUser = User::withoutGlobalScopes()->where('email', $email)->first();
            
            if ($existingUser) {
                Log::info('CrossTenantUserService - Usuário já existe no tenant, verificando vínculo e role', [
                    'user_id' => $existingUser->id,
                    'email' => $email,
                    'tenant_id' => $targetTenantId,
                ]);

                // 🔥 Garantir que a role esteja correta (mesmo para usuários existentes)
                try {
                    // Verificar se a role existe no tenant, se não, rodar o seeder
                    $roleExists = DB::table('roles')->where('name', $roleMapped)->exists();
                    if (!$roleExists) {
                        Log::info("CrossTenantUserService - Role '{$roleMapped}' não encontrada no tenant {$targetTenantId}. Executando seeder.");
                        (new \Database\Seeders\RolesPermissionsSeeder())->run();
                    }

                    // Sincronizar role - Usamos DB direto para garantir que persistiu no banco do tenant correto
                    // às vezes o Spatie syncRoles tem problemas com cache/contexto de tenancy
                    $roleId = DB::table('roles')->where('name', $roleMapped)->value('id');
                    if ($roleId) {
                        DB::table('model_has_roles')->updateOrInsert(
                            [
                                'model_id' => $existingUser->id,
                                'model_type' => get_class($existingUser),
                            ],
                            ['role_id' => $roleId]
                        );
                        Log::info("CrossTenantUserService - Role '{$roleMapped}' (ID: {$roleId}) sincronizada para usuário {$existingUser->id}");
                    }
                } catch (\Exception $e) {
                    Log::warning('CrossTenantUserService - Erro ao sincronizar role para usuário existente', [
                        'user_id' => $existingUser->id,
                        'role' => $roleMapped,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Vincular à empresa se não estiver vinculado
                if ($targetEmpresaId) {
                    $this->vincularUsuarioAEmpresa($existingUser->id, $targetEmpresaId, $roleMapped);
                } else {
                    // Vincular à primeira empresa disponível
                    $primeiraEmpresa = Empresa::first();
                    if ($primeiraEmpresa) {
                        $this->vincularUsuarioAEmpresa($existingUser->id, $primeiraEmpresa->id, $roleMapped);
                        $targetEmpresaId = $primeiraEmpresa->id;
                    }
                }
                
                return $existingUser->id;
            }
            
            // Criar novo usuário no tenant de destino
            $senhaFinal = $password ?? Str::random(12);
            
            $newUser = User::create([
                'name' => $sourceUserData['name'] ?? explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make($senhaFinal),
                'empresa_ativa_id' => $targetEmpresaId,
            ]);
            
            Log::info('CrossTenantUserService - Novo usuário criado no tenant', [
                'user_id' => $newUser->id,
                'email' => $email,
                'tenant_id' => $targetTenantId,
            ]);
            
            // Atribuir role
            try {
                // Verificar se a role existe no tenant, se não, rodar o seeder
                $roleExists = DB::table('roles')->where('name', $roleMapped)->exists();
                if (!$roleExists) {
                    Log::info("CrossTenantUserService - Role '{$roleMapped}' não encontrada no tenant {$targetTenantId}. Executando seeder.");
                    (new \Database\Seeders\RolesPermissionsSeeder())->run();
                }

                $roleId = DB::table('roles')->where('name', $roleMapped)->value('id');
                if ($roleId) {
                    DB::table('model_has_roles')->updateOrInsert(
                        [
                            'model_id' => $newUser->id,
                            'model_type' => get_class($newUser),
                        ],
                        ['role_id' => $roleId]
                    );
                    Log::info("CrossTenantUserService - Role '{$roleMapped}' (ID: {$roleId}) atribuída ao novo usuário {$newUser->id}");
                }
            } catch (\Exception $e) {
                Log::warning('CrossTenantUserService - Erro ao atribuir role', [
                    'user_id' => $newUser->id,
                    'role' => $roleMapped,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Vincular à empresa
            if ($targetEmpresaId) {
                $this->vincularUsuarioAEmpresa($newUser->id, $targetEmpresaId, $roleMapped);
            } else {
                // Vincular à primeira empresa disponível
                $primeiraEmpresa = Empresa::first();
                if ($primeiraEmpresa) {
                    $this->vincularUsuarioAEmpresa($newUser->id, $primeiraEmpresa->id, $roleMapped);
                    $targetEmpresaId = $primeiraEmpresa->id;
                }
            }
            
            return $newUser->id;
        });

        // 4. Registrar na users_lookup (banco central)
        $empresa = null;
        if ($targetEmpresaId) {
            $empresa = $this->adminTenancyRunner->runForTenant($targetTenantDomain, function () use ($targetEmpresaId) {
                return Empresa::find($targetEmpresaId);
            });
        }
        
        $cnpj = $empresa->cnpj ?? $targetTenant->cnpj ?? '00000000000000';
        
        try {
            $this->usersLookupService->registrar(
                tenantId: $targetTenantId,
                userId: $userId,
                empresaId: $targetEmpresaId,
                email: $email,
                cnpj: $cnpj,
            );
            
            Log::info('CrossTenantUserService - Registro em users_lookup criado', [
                'email' => $email,
                'tenant_id' => $targetTenantId,
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            Log::error('CrossTenantUserService - Erro ao registrar em users_lookup', [
                'email' => $email,
                'tenant_id' => $targetTenantId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            // Não falhar por causa do lookup - o vínculo principal já foi criado
        }

        // 5. Garantir mapeamento tenant_empresa existe
        if ($targetEmpresaId) {
            TenantEmpresa::createOrUpdateMapping($targetTenantId, $targetEmpresaId);
        }

        return [
            'user_id' => $userId,
            'tenant_id' => $targetTenantId,
            'empresa_id' => $targetEmpresaId,
            'email' => $email,
            'role' => $role,
            'is_new_user' => !$sourceUserData,
        ];
    }

    /**
     * Desvincular usuário de um tenant
     * 
     * Remove o usuário da empresa e do lookup, mas mantém o registro no tenant
     * para preservar histórico.
     */
    public function desvincularUsuarioDeTenant(
        string $email,
        int $tenantId,
    ): bool {
        Log::info('CrossTenantUserService::desvincularUsuarioDeTenant - Iniciando', [
            'email' => $email,
            'tenant_id' => $tenantId,
        ]);

        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenantDomain) {
            throw new \Exception("Tenant {$tenantId} não encontrado.");
        }

        // 1. Buscar o usuário no tenant
        $userId = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($user) {
                // Remover todos os vínculos com empresas
                DB::table('empresa_user')->where('user_id', $user->id)->delete();
                return $user->id;
            }
            return null;
        });

        if (!$userId) {
            Log::warning('CrossTenantUserService - Usuário não encontrado no tenant', [
                'email' => $email,
                'tenant_id' => $tenantId,
            ]);
            return false;
        }

        // 2. Inativar na users_lookup
        try {
            $this->usersLookupService->inativar($email, $tenantId, $userId);
        } catch (\Exception $e) {
            Log::error('CrossTenantUserService - Erro ao inativar lookup', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Soft delete do usuário no tenant para que não apareça mais nas telas de usuários
        try {
            $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                $user = User::withoutGlobalScopes()->find($userId);
                if ($user && !$user->trashed()) {
                    $user->delete();
                }
            });
        } catch (\Exception $e) {
            Log::error('CrossTenantUserService - Erro ao soft-deletar usuário ao desvincular', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('CrossTenantUserService - Usuário desvinculado com sucesso', [
            'email' => $email,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Listar todos os tenants onde um email está cadastrado
     */
    public function listarTenantsDoUsuario(string $email): array
    {
        $lookups = $this->usersLookupService->encontrarPorEmail($email);
        
        $tenants = [];
        foreach ($lookups as $lookup) {
            $tenantDomain = $this->tenantRepository->buscarPorId($lookup->tenantId);
            if ($tenantDomain) {
                $tenants[] = [
                    'tenant_id' => $tenantDomain->id,
                    'razao_social' => $tenantDomain->razaoSocial,
                    'cnpj' => $tenantDomain->cnpj ?? null,
                    'user_id' => $lookup->userId,
                    'empresa_id' => $lookup->empresaId,
                    'status' => $lookup->status,
                ];
            }
        }

        return $tenants;
    }

    /**
     * Trocar o tenant ativo do usuário (gera novo JWT)
     * 
     * Usado para alternar entre tenants sem re-login.
     * Valida que o usuário realmente pertence ao tenant solicitado.
     */
    public function trocarTenantAtivo(int $userId, string $email, int $newTenantId): array
    {
        Log::info('CrossTenantUserService::trocarTenantAtivo - Iniciando', [
            'user_id' => $userId,
            'email' => $email,
            'new_tenant_id' => $newTenantId,
        ]);

        // Validar que o usuário pertence ao tenant solicitado
        $tenantDomain = $this->tenantRepository->buscarPorId($newTenantId);
        if (!$tenantDomain) {
            throw new \Exception("Tenant {$newTenantId} não encontrado.");
        }

        // Verificar que o usuário existe no tenant
        $userDataNoTenant = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
            if (!$user || $user->trashed()) {
                return null;
            }
            
            // Buscar empresa ativa
            $empresaAtiva = $user->empresas()->first();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_ativa_id' => $empresaAtiva?->id ?? $user->empresa_ativa_id,
            ];
        });

        if (!$userDataNoTenant) {
            throw new \Exception("Usuário não encontrado no tenant {$newTenantId}.");
        }

        // Gerar novo JWT com tenant_id atualizado
        $jwtService = app(\App\Services\JWTService::class);
        $token = $jwtService->generateToken([
            'user_id' => $userDataNoTenant['id'],
            'tenant_id' => $newTenantId,
            'empresa_id' => $userDataNoTenant['empresa_ativa_id'],
            'role' => null,
        ]);

        $tenant = Tenant::find($newTenantId);

        return [
            'user' => $userDataNoTenant,
            'tenant' => [
                'id' => $newTenantId,
                'razao_social' => $tenant->razao_social ?? $tenantDomain->razaoSocial,
            ],
            'empresa' => $userDataNoTenant['empresa_ativa_id'] ? [
                'id' => $userDataNoTenant['empresa_ativa_id'],
            ] : null,
            'token' => $token,
        ];
    }

    /**
     * Buscar dados de um usuário em qualquer tenant onde ele exista
     */
    private function buscarUsuarioEmQualquerTenant(string $email): ?array
    {
        $lookups = $this->usersLookupService->encontrarPorEmail($email);
        
        if (empty($lookups)) {
            return null;
        }

        $firstLookup = $lookups[0];
        $tenantDomain = $this->tenantRepository->buscarPorId($firstLookup->tenantId);
        
        if (!$tenantDomain) {
            return null;
        }

        return $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
            if (!$user) {
                return null;
            }
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });
    }

    /**
     * Vincular usuário a empresa (tabela pivot)
     */
    private function vincularUsuarioAEmpresa(int $userId, int $empresaId, string $role): void
    {
        // Normalizar perfil para lowercase (ex: Administrador -> administrador)
        $perfil = strtolower($role);

        $exists = DB::table('empresa_user')
            ->where('user_id', $userId)
            ->where('empresa_id', $empresaId)
            ->exists();

        if (!$exists) {
            DB::table('empresa_user')->insert([
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'perfil' => $perfil,
                'criado_em' => now(),
                'atualizado_em' => now(),
            ]);

            Log::info('CrossTenantUserService - Vínculo empresa_user criado', [
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'perfil' => $perfil,
            ]);
        } else {
            // Atualizar perfil se já existir
            DB::table('empresa_user')
                ->where('user_id', $userId)
                ->where('empresa_id', $empresaId)
                ->update([
                    'perfil' => $perfil,
                    'atualizado_em' => now(),
                ]);

            Log::debug('CrossTenantUserService - Vínculo empresa_user atualizado', [
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'perfil' => $perfil,
            ]);
        }
    }
}
