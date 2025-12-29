<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\RegisterDTO;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Models\Tenant;
use DomainException;

/**
 * Use Case: Registro de Usuário
 * Reutiliza CriarUsuarioUseCase mas adiciona criação de token
 */
class RegisterUseCase
{
    public function __construct(
        private CriarUsuarioUseCase $criarUsuarioUseCase,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(RegisterDTO $dto): array
    {
        // Buscar tenant
        $tenant = Tenant::find($dto->tenantId);
        
        if (!$tenant) {
            throw new DomainException('Tenant não encontrado.');
        }

        // Criar TenantContext
        $context = TenantContext::create($tenant->id);

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

        // Buscar empresa ativa
        $empresaAtiva = null;
        if ($user->empresaAtivaId) {
            // Buscar empresa através do repository (precisa estar no contexto do tenant)
            tenancy()->initialize($tenant);
            try {
                $empresaRepository = app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class);
                $empresaAtiva = $empresaRepository->buscarPorId($user->empresaAtivaId);
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        // Criar token (infraestrutura - Sanctum)
        tenancy()->initialize($tenant);
        try {
            $userModel = \App\Modules\Auth\Models\User::find($user->id);
            $token = $userModel->createToken('api-token', ['tenant_id' => $tenant->id])->plainTextToken;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->nome,
                'email' => $user->email,
                'empresa_ativa_id' => $user->empresaAtivaId,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
            ],
            'empresa' => $empresaAtiva ? [
                'id' => $empresaAtiva->id,
                'razao_social' => $empresaAtiva->razaoSocial,
            ] : null,
            'token' => $token,
        ];
    }
}

