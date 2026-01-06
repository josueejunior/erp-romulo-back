<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\RegisterDTO;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use DomainException;

/**
 * Use Case: Registro de Usuário
 * Reutiliza CriarUsuarioUseCase mas adiciona criação de token
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository e AdminTenancyRunner
 */
class RegisterUseCase
{
    public function __construct(
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(RegisterDTO $dto): array
    {
        // Buscar tenant usando repository (Domain, não Eloquent)
        $tenantDomain = $this->tenantRepository->buscarPorId($dto->tenantId);
        
        if (!$tenantDomain) {
            throw new DomainException('Tenant não encontrado.');
        }

        // Converter Domain Entity para Model (necessário para algumas operações)
        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
        if (!$tenantModel) {
            throw new DomainException('Tenant não encontrado.');
        }

        // Criar TenantContext
        $context = TenantContext::create($tenantDomain->id);

        // Criar DTO para CriarUsuarioUseCase
        $criarUsuarioDTO = new CriarUsuarioDTO(
            nome: $dto->nome,
            email: $dto->email,
            senha: $dto->senha,
            empresaId: $dto->empresaId,
            role: $dto->role,
            empresas: $dto->empresas,
        );

        // Executar criação de usuário
        $user = $this->criarUsuarioUseCase->executar($criarUsuarioDTO, $context);

        // Buscar empresa ativa e criar token usando AdminTenancyRunner
        $empresaAtiva = null;
        $token = null;

        // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
        $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($user, $tenantDomain, &$empresaAtiva, &$token) {
            // Buscar empresa ativa através do repository
            if ($user->empresaAtivaId) {
                $empresaRepository = app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class);
                $empresaAtiva = $empresaRepository->buscarPorId($user->empresaAtivaId);
            }

            // Criar token (infraestrutura - Sanctum)
            $userModel = \App\Modules\Auth\Models\User::find($user->id);
            if ($userModel) {
                $token = $userModel->createToken('api-token', ['tenant_id' => $tenantDomain->id])->plainTextToken;
            }
        });

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->nome,
                'email' => $user->email,
                'empresa_ativa_id' => $user->empresaAtivaId,
            ],
            'tenant' => [
                'id' => $tenantDomain->id,
                'razao_social' => $tenantDomain->razaoSocial,
            ],
            'empresa' => $empresaAtiva ? [
                'id' => $empresaAtiva->id,
                'razao_social' => $empresaAtiva->razaoSocial,
            ] : null,
            'token' => $token,
        ];
    }
}





