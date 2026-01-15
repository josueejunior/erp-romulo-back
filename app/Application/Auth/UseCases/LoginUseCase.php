<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\LoginDTO;
use App\Application\CadastroPublico\Services\UsersLookupService;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Senha;
use App\Services\AdminTenancyRunner;
use App\Models\Tenant;
use App\Modules\Auth\Models\AdminUser;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case: Login de Usuário
 * Orquestra o login, mas não sabe nada de banco de dados diretamente
 * 
 * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
 */
class LoginUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
        private UsersLookupService $usersLookupService,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(LoginDTO $dto): array
    {
        \Log::info('LoginUseCase::executar - Iniciando', [
            'email' => $dto->email,
            'has_tenant_id' => !empty($dto->tenantId),
        ]);
        
        try {
            // Validar email usando Value Object
            \Log::debug('LoginUseCase::executar - Criando Email Value Object');
            $email = Email::criar($dto->email);
            \Log::debug('LoginUseCase::executar - Email Value Object criado', ['email' => $email->value]);

            // Se tenant_id não foi fornecido, tentar detectar automaticamente
            $tenant = null;
            if ($dto->tenantId) {
                \Log::debug('LoginUseCase::executar - Buscando tenant por ID', ['tenant_id' => $dto->tenantId]);
                // 🔥 ARQUITETURA LIMPA: Usar TenantRepository em vez de Eloquent direto
                $tenantDomain = $this->tenantRepository->buscarPorId($dto->tenantId);
                if (!$tenantDomain) {
                    throw new DomainException('Tenant não encontrado.');
                }
                // Converter para Model (necessário para tenancy()->initialize())
                $tenant = $this->tenantRepository->buscarModeloPorId($dto->tenantId);
                if (!$tenant) {
                    throw new DomainException('Tenant não encontrado.');
                }
            } else {
                \Log::debug('LoginUseCase::executar - Buscando tenant automaticamente por email');
                // ⚡ REFATORADO: Usar users_lookup para busca O(1) ao invés de O(n)
                $lookups = $this->usersLookupService->encontrarPorEmail($email->value);
                
                if (empty($lookups)) {
                    // Fallback: Se não encontrar em users_lookup, usar busca antiga (para dados antigos)
                    \Log::warning('LoginUseCase::executar - Usuário não encontrado em users_lookup, usando busca antiga', [
                        'email' => $email->value,
                    ]);
                    $tenant = $this->buscarTenantPorEmail($email->value);
                    if (!$tenant) {
                        throw new DomainException('Usuário não encontrado em nenhum tenant. Verifique suas credenciais.');
                    }
                } else {
                    // 🔥 SEGURANÇA/UX: Se encontrar múltiplos tenants, retornar lista para seleção
                    if (count($lookups) > 1) {
                        \Log::info('LoginUseCase::executar - Múltiplos tenants encontrados para este email', [
                            'email' => $email->value,
                            'count' => count($lookups),
                            'tenant_ids' => array_map(fn($l) => $l->tenantId, $lookups),
                        ]);
                        
                        // Buscar informações dos tenants para exibir ao usuário
                        $tenantsInfo = [];
                        foreach ($lookups as $lookup) {
                            $tenantDomain = $this->tenantRepository->buscarPorId($lookup->tenantId);
                            if ($tenantDomain) {
                                $tenantsInfo[] = [
                                    'tenant_id' => $tenantDomain->id,
                                    'razao_social' => $tenantDomain->razaoSocial,
                                    'cnpj' => $tenantDomain->cnpj,
                                    'user_id' => $lookup->userId,
                                ];
                            }
                        }
                        
                        // Retornar resposta especial para múltiplos tenants
                        // O frontend deve exibir tela de seleção
                        throw new \App\Domain\Exceptions\MultiplosTenantsException(
                            'Este email está associado a múltiplas empresas. Selecione qual deseja acessar.',
                            $tenantsInfo
                        );
                    }
                    
                    $lookup = $lookups[0];
                    $tenantDomain = $this->tenantRepository->buscarPorId($lookup->tenantId);
                    
                    if (!$tenantDomain) {
                        throw new DomainException('Tenant não encontrado.');
                    }
                    
                    $tenant = $this->tenantRepository->buscarModeloPorId($lookup->tenantId);
                    if (!$tenant) {
                        throw new DomainException('Tenant não encontrado.');
                    }
                    
                    \Log::info('LoginUseCase::executar - Tenant encontrado via users_lookup', [
                        'tenant_id' => $lookup->tenantId,
                        'user_id' => $lookup->userId,
                        'email' => $email->value,
                    ]);
                }
            }

            \Log::debug('LoginUseCase::executar - Inicializando tenancy', ['tenant_id' => $tenant->id]);
            // Inicializar contexto do tenant
            tenancy()->initialize($tenant);

            // Buscar usuário no banco do tenant através do repository
            \Log::debug('LoginUseCase::executar - Buscando usuário por email');
            $user = $this->userRepository->buscarPorEmail($email->value);

            // 🔥 MELHORIA: Prevenir timing attacks - sempre verificar senha mesmo se usuário não existir
            $isValidPassword = false;
            if ($user) {
                // Validar senha usando Value Object
                \Log::debug('LoginUseCase::executar - Validando senha');
                $senha = new Senha($user->senhaHash);
                $isValidPassword = $senha->verificar($dto->password);
            } else {
                // Se usuário não existe, ainda assim verificar senha com hash dummy para manter tempo constante
                // Isso previne timing attacks que revelam se email existe
                \Log::debug('LoginUseCase::executar - Usuário não encontrado, verificando senha dummy');
                $dummyHash = '$2y$10$dummyhashforsecuritytimingattackprevention';
                Hash::check($dto->password, $dummyHash);
            }

            if (!$user || !$isValidPassword) {
                throw new DomainException('Credenciais inválidas.');
            }

            // Obter empresa ativa do usuário
            \Log::debug('LoginUseCase::executar - Buscando empresa ativa');
            $empresaAtiva = $this->userRepository->buscarEmpresaAtiva($user->id);
            
            // Se não tem empresa ativa, buscar primeira empresa
            if (!$empresaAtiva) {
                $empresas = $this->userRepository->buscarEmpresas($user->id);
                $empresaAtiva = !empty($empresas) ? $empresas[0] : null;
                
                if ($empresaAtiva) {
                    // Atualizar empresa ativa
                    $user = $this->userRepository->atualizarEmpresaAtiva($user->id, $empresaAtiva->id);
                }
            }

            // 🔥 ARQUITETURA RIGOROSA: Validar consistência usuário-empresa-tenant
            // REGRA: Se empresa e usuário estão em tenants diferentes, o tenant da empresa manda
            // (se usuário tiver permissão na empresa)
            $tenantCorreto = $tenant; // Fallback: usar tenant onde usuário foi encontrado
            
            if ($empresaAtiva) {
                \Log::debug('LoginUseCase::executar - Buscando tenant correto por empresa', ['empresa_id' => $empresaAtiva->id]);
                $tenantDaEmpresa = $this->buscarTenantPorEmpresa($empresaAtiva->id);
                
                if ($tenantDaEmpresa && $tenantDaEmpresa->id !== $tenant->id) {
                    // ⚠️ INCONSISTÊNCIA DETECTADA: Empresa está em tenant diferente
                    \Log::warning('LoginUseCase - ⚠️ INCONSISTÊNCIA: Empresa ativa está em tenant diferente', [
                        'empresa_id' => $empresaAtiva->id,
                        'tenant_id_usuario' => $tenant->id,
                        'tenant_id_empresa' => $tenantDaEmpresa->id,
                    ]);
                    
                    // Verificar se usuário tem permissão na empresa (através da tabela pivot)
                    $usuarioTemPermissaoNaEmpresa = $this->verificarPermissaoUsuarioEmpresa($user->id, $empresaAtiva->id, $tenantDaEmpresa->id);
                    
                    if ($usuarioTemPermissaoNaEmpresa) {
                        // ✅ DECISÃO: Usuário tem permissão → Tenant da Empresa manda
                        $tenantCorreto = $tenantDaEmpresa;
                        
                        // Verificar se usuário existe no tenant da empresa
                        $usuarioExisteNoTenantEmpresa = $this->verificarUsuarioExisteNoTenant($user->id, $tenantDaEmpresa->id);
                        
                        if (!$usuarioExisteNoTenantEmpresa) {
                            // Usuário não existe no tenant da empresa, mas tem permissão
                            // Isso indica inconsistência de dados - logar para auditoria
                            \Log::warning('LoginUseCase - ⚠️ Usuário tem permissão na empresa mas não existe no tenant da empresa', [
                                'user_id' => $user->id,
                                'empresa_id' => $empresaAtiva->id,
                                'tenant_id_empresa' => $tenantDaEmpresa->id,
                                'acao' => 'Usando tenant da empresa mesmo sem usuário existir lá (pode causar problemas)',
                            ]);
                        } else {
                            \Log::info('LoginUseCase - ✅ Usuário tem permissão e existe no tenant da empresa', [
                                'tenant_id' => $tenantCorreto->id,
                                'empresa_id' => $empresaAtiva->id,
                            ]);
                        }
                    } else {
                        // ❌ Usuário NÃO tem permissão na empresa → FALHAR LOGIN
                        \Log::error('LoginUseCase - ❌ Usuário sem acesso à empresa ativa configurada', [
                            'user_id' => $user->id,
                            'empresa_id' => $empresaAtiva->id,
                            'tenant_id_usuario' => $tenant->id,
                            'tenant_id_empresa' => $tenantDaEmpresa->id,
                        ]);
                        
                        throw new DomainException(
                            'Usuário sem acesso à empresa ativa configurada. ' .
                            'A empresa está em outro tenant e você não tem permissão para acessá-la. ' .
                            'Entre em contato com o administrador.'
                        );
                    }
                } else if (!$tenantDaEmpresa) {
                    // Empresa não encontrada em nenhum tenant - usar tenant do usuário
                    $tenantCorreto = $tenant;
                    \Log::warning('LoginUseCase - Empresa ativa não encontrada em nenhum tenant, usando tenant do usuário', [
                        'empresa_id' => $empresaAtiva->id,
                        'tenant_id_fallback' => $tenant->id,
                    ]);
                } else {
                    // ✅ IDEAL: Empresa e usuário estão no mesmo tenant
                    $tenantCorreto = $tenant;
                    \Log::debug('LoginUseCase - ✅ Empresa e usuário estão no mesmo tenant', [
                        'tenant_id' => $tenant->id,
                        'empresa_id' => $empresaAtiva->id,
                    ]);
                }
            }

            // 🔥 JWT STATELESS: Gerar token JWT em vez de Sanctum
            \Log::debug('LoginUseCase::executar - Gerando token JWT');
            $jwtService = app(\App\Services\JWTService::class);
            
            $tokenPayload = [
                'user_id' => $user->id,
                'tenant_id' => $tenantCorreto->id,
                'empresa_id' => $empresaAtiva?->id,
                'role' => null, // Pode ser adicionado se necessário
            ];
            
            $token = $jwtService->generateToken($tokenPayload);

            \Log::info('LoginUseCase::executar - Login realizado com sucesso', [
                'user_id' => $user->id,
                'tenant_id' => $tenantCorreto->id,
            ]);

            // Buscar modelo completo do usuário para foto_perfil (se necessário)
            $userModel = $this->userRepository->buscarModeloPorId($user->id);
            
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresaAtivaId,
                    'foto_perfil' => $userModel?->foto_perfil ?? null,
                ],
                'tenant' => [
                    'id' => $tenantCorreto->id,
                    'razao_social' => $tenantCorreto->razao_social,
                ],
                'empresa' => $empresaAtiva ? [
                    'id' => $empresaAtiva->id,
                    'razao_social' => $empresaAtiva->razaoSocial,
                ] : null,
                'token' => $token, // JWT token stateless
            ];
        } catch (\Exception $e) {
            \Log::error('LoginUseCase::executar - Erro capturado', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'previous' => $e->getPrevious() ? [
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ] : null,
            ]);
            throw $e; // Re-lançar para ser capturado pelo controller
        } finally {
            // Finalizar contexto do tenant
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Buscar tenant automaticamente pelo email do usuário
     * Itera por todos os tenants procurando o usuário
     * 
     * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
     */
    private function buscarTenantPorEmail(string $email): ?Tenant
    {
        \Log::debug('LoginUseCase::buscarTenantPorEmail - Iniciando busca', ['email' => $email]);
        
        // Buscar todos os tenants usando repository (Domain, não Eloquent)
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'per_page' => 1000, // Buscar todos
        ]);
        
        \Log::debug('LoginUseCase::buscarTenantPorEmail - Tenants encontrados', [
            'total' => $tenantsPaginator->total(),
        ]);
        
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                $user = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
                    // Tentar buscar usuário neste tenant
                    return $this->userRepository->buscarPorEmail($email);
                });
                
                if ($user) {
                    \Log::info('LoginUseCase::buscarTenantPorEmail - Usuário encontrado', [
                        'tenant_id' => $tenantDomain->id,
                        'user_id' => $user->id,
                    ]);
                    // Converter Domain Entity para Model (necessário para tenancy()->initialize())
                    $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    return $tenantModel; // Usuário encontrado neste tenant
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                \Log::warning("Erro ao buscar usuário no tenant {$tenantDomain->id}: " . $e->getMessage());
                // AdminTenancyRunner já garantiu finalização do tenancy no finally
                continue;
            }
        }
        
        \Log::warning('LoginUseCase::buscarTenantPorEmail - Usuário não encontrado em nenhum tenant', [
            'email' => $email,
        ]);
        return null; // Usuário não encontrado em nenhum tenant
    }

    /**
     * Buscar tenant correto baseado na empresa ativa
     * Itera por todos os tenants procurando a empresa
     * 
     * 🔥 CRÍTICO: Garante que o tenant retornado seja o correto da empresa ativa,
     * não apenas onde o usuário foi encontrado
     * 
     * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
     */
    private function buscarTenantPorEmpresa(int $empresaId): ?Tenant
    {
        // Buscar todos os tenants usando repository (Domain, não Eloquent)
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'per_page' => 1000, // Buscar todos
        ]);
        
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                $empresa = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                    // Tentar buscar empresa neste tenant
                    return \App\Models\Empresa::find($empresaId);
                });
                
                if ($empresa) {
                    // Converter Domain Entity para Model (necessário para tenancy()->initialize())
                    $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    return $tenantModel; // Empresa encontrada neste tenant
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                \Log::debug("Erro ao buscar empresa no tenant {$tenantDomain->id}: " . $e->getMessage());
                // AdminTenancyRunner já garantiu finalização do tenancy no finally
                continue;
            }
        }
        
        return null; // Empresa não encontrada em nenhum tenant
    }

    /**
     * Verificar se o usuário existe em um tenant específico
     * 
     * @param int $userId
     * @param int $tenantId
     * @return bool
     */
    private function verificarUsuarioExisteNoTenant(int $userId, int $tenantId): bool
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (!$tenantDomain) {
                return false;
            }
            
            $usuarioExiste = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                $user = \App\Modules\Auth\Models\User::find($userId);
                return $user !== null && !$user->trashed();
            });
            
            return $usuarioExiste ?? false;
        } catch (\Exception $e) {
            \Log::warning('LoginUseCase::verificarUsuarioExisteNoTenant - Erro ao verificar', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verificar se o usuário tem permissão para acessar uma empresa específica
     * Verifica através da tabela pivot empresa_user no tenant da empresa
     * 
     * @param int $userId
     * @param int $empresaId
     * @param int $tenantId
     * @return bool
     */
    private function verificarPermissaoUsuarioEmpresa(int $userId, int $empresaId, int $tenantId): bool
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (!$tenantDomain) {
                return false;
            }
            
            $temPermissao = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId, $empresaId) {
                // Verificar se existe registro na tabela pivot empresa_user
                $pivotExiste = \Illuminate\Support\Facades\DB::table('empresa_user')
                    ->where('user_id', $userId)
                    ->where('empresa_id', $empresaId)
                    ->exists();
                
                return $pivotExiste;
            });
            
            return $temPermissao ?? false;
        } catch (\Exception $e) {
            \Log::warning('LoginUseCase::verificarPermissaoUsuarioEmpresa - Erro ao verificar', [
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

