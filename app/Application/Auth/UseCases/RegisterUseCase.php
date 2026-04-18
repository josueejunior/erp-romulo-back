<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\RegisterDTO;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use App\Services\TenantService;
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
        private TenantService $tenantService,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(RegisterDTO $dto): array
    {
        $tenantId = $dto->tenantId;
        $empresaId = $dto->empresaId;

        // Se não vier tenant/empresa, criar automaticamente.
        if (!$tenantId || !$empresaId) {
            $cnpjBase = str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT);
            $cnpjMascara = substr($cnpjBase, 0, 2) . '.' .
                substr($cnpjBase, 2, 3) . '.' .
                substr($cnpjBase, 5, 3) . '/' .
                substr($cnpjBase, 8, 4) . '-' .
                substr($cnpjBase, 12, 2);

            $autoTenantResult = $this->tenantService->createTenantWithEmpresa([
                'razao_social' => "Empresa {$dto->nome}",
                'cnpj' => $cnpjMascara,
                'email' => $dto->email,
                'telefone' => '(11) 99999-9999',
                'status' => 'ativa',
            ], false);

            $tenantModelAuto = $autoTenantResult['tenant'] ?? null;
            $empresaModelAuto = $autoTenantResult['empresa'] ?? null;

            if (!$tenantModelAuto || !$empresaModelAuto) {
                throw new DomainException('Falha ao criar tenant automaticamente para o usuário.');
            }

            $tenantId = (string) $tenantModelAuto->id;
            $empresaId = (int) $empresaModelAuto->id;
        }

        // Buscar tenant usando repository (Domain, não Eloquent)
        $tenantDomain = $this->tenantRepository->buscarPorId((int) $tenantId);
        
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
            empresaId: $empresaId,
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
                // 🔥 JWT STATELESS: Gerar token JWT em vez de Sanctum
                $jwtService = app(\App\Services\JWTService::class);
                $token = $jwtService->generateToken([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantDomain->id,
                    'empresa_id' => $empresaAtiva?->id,
                ]);
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





