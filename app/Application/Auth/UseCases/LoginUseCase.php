<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\LoginDTO;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Senha;
use App\Services\AdminTenancyRunner;
use App\Models\Tenant;
use App\Modules\Auth\Models\AdminUser;
use DomainException;

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
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(LoginDTO $dto): array
    {
        // Validar email usando Value Object
        $email = Email::criar($dto->email);

        // Se tenant_id não foi fornecido, tentar detectar automaticamente
        $tenant = null;
        if ($dto->tenantId) {
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
            // Buscar tenant automaticamente pelo email
            $tenant = $this->buscarTenantPorEmail($email->value);
            if (!$tenant) {
                throw new DomainException('Usuário não encontrado em nenhum tenant. Verifique suas credenciais.');
            }
        }

        // Inicializar contexto do tenant
        tenancy()->initialize($tenant);

        try {
            // Buscar usuário no banco do tenant através do repository
            $user = $this->userRepository->buscarPorEmail($email->value);

            if (!$user) {
                throw new DomainException('Credenciais inválidas.');
            }

            // Validar senha usando Value Object
            $senha = new Senha($user->senhaHash);
            if (!$senha->verificar($dto->password)) {
                throw new DomainException('Credenciais inválidas.');
            }

            // Obter empresa ativa do usuário
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

            // 🔥 CRÍTICO: Buscar tenant correto baseado na empresa ativa
            // A empresa ativa pode estar em outro tenant que não o onde o usuário foi encontrado
            $tenantCorreto = $tenant; // Fallback: usar tenant onde usuário foi encontrado
            if ($empresaAtiva) {
                $tenantCorreto = $this->buscarTenantPorEmpresa($empresaAtiva->id);
                if (!$tenantCorreto) {
                    // Se não encontrou, usar o tenant onde o usuário foi encontrado
                    $tenantCorreto = $tenant;
                    \Log::warning('LoginUseCase - Empresa ativa não encontrada em nenhum tenant, usando tenant do usuário', [
                        'empresa_id' => $empresaAtiva->id,
                        'tenant_id_fallback' => $tenant->id,
                    ]);
                } else if ($tenantCorreto->id !== $tenant->id) {
                    \Log::info('LoginUseCase - Tenant correto encontrado baseado na empresa ativa', [
                        'empresa_id' => $empresaAtiva->id,
                        'tenant_id_usuario' => $tenant->id,
                        'tenant_id_empresa' => $tenantCorreto->id,
                    ]);
                }
            }

            // Criar token (infraestrutura - Sanctum)
            // Nota: Token é criado no modelo Eloquent, mas isso é aceitável pois é detalhe de infraestrutura
            $userModel = \App\Modules\Auth\Models\User::find($user->id);
            $token = $userModel->createToken('api-token', ['tenant_id' => $tenantCorreto->id])->plainTextToken;

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresaAtivaId,
                    'foto_perfil' => $userModel->foto_perfil ?? null,
                ],
                'tenant' => [
                    'id' => $tenantCorreto->id,
                    'razao_social' => $tenantCorreto->razao_social,
                ],
                'empresa' => $empresaAtiva ? [
                    'id' => $empresaAtiva->id,
                    'razao_social' => $empresaAtiva->razaoSocial,
                ] : null,
                'token' => $token,
            ];
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
        // Buscar todos os tenants usando repository (Domain, não Eloquent)
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'per_page' => 1000, // Buscar todos
        ]);
        
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                $user = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
                    // Tentar buscar usuário neste tenant
                    return $this->userRepository->buscarPorEmail($email);
                });
                
                if ($user) {
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
}

