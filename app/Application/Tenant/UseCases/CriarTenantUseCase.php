<?php

namespace App\Application\Tenant\UseCases;

use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Domain\Tenant\Services\TenantRolesServiceInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case: Criar Tenant com Empresa e opcionalmente Usuário Admin
 * 
 * Coordena o fluxo de criação, mas não sabe nada de banco de dados
 */
class CriarTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantDatabaseServiceInterface $databaseService,
        private TenantRolesServiceInterface $rolesService,
        private EmpresaRepositoryInterface $empresaRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarTenantDTO $dto, bool $requireAdmin = false): array
    {
        // Validar se admin é obrigatório
        if ($requireAdmin && !$dto->temDadosAdmin()) {
            throw new DomainException('Dados do administrador são obrigatórios.');
        }

        // Criar entidade Tenant (regras de negócio)
        $tenant = new Tenant(
            id: null, // Será gerado pelo repository
            razaoSocial: $dto->razaoSocial,
            cnpj: $dto->cnpj,
            email: $dto->email,
            status: $dto->status,
            endereco: $dto->endereco,
            cidade: $dto->cidade,
            estado: $dto->estado,
            cep: $dto->cep,
            telefones: $dto->telefones,
            emailsAdicionais: $dto->emailsAdicionais,
            banco: $dto->banco,
            agencia: $dto->agencia,
            conta: $dto->conta,
            tipoConta: $dto->tipoConta,
            pix: $dto->pix,
            representanteLegalNome: $dto->representanteLegalNome,
            representanteLegalCpf: $dto->representanteLegalCpf,
            representanteLegalCargo: $dto->representanteLegalCargo,
            logo: $dto->logo,
        );

        // Persistir tenant (infraestrutura)
        $tenant = $this->tenantRepository->criar($tenant);

        try {
            // Criar banco de dados do tenant (infraestrutura)
            $this->databaseService->criarBancoDados($tenant);
            // Executar migrations
            $this->databaseService->executarMigrations($tenant);
        } catch (\Exception $e) {
            // Se falhar, deletar o tenant criado
            try {
                $this->tenantRepository->deletar($tenant->id);
            } catch (\Exception $deleteException) {
                Log::warning('Erro ao deletar tenant após falha na criação do banco', [
                    'tenant_id' => $tenant->id,
                    'error' => $deleteException->getMessage(),
                ]);
            }
            throw new DomainException('Erro ao criar o banco de dados da empresa: ' . $e->getMessage());
        }

        // Inicializar contexto do tenant
        tenancy()->initialize(\App\Models\Tenant::find($tenant->id));

        try {
            // Inicializar roles e permissões
            $this->rolesService->inicializarRoles($tenant);

            // Criar empresa dentro do tenant
            $empresa = $this->empresaRepository->criarNoTenant($tenant->id, $dto);

            $adminUser = null;

            // Se dados do admin foram fornecidos, criar usuário administrador
            if ($dto->temDadosAdmin()) {
                $adminUser = $this->userRepository->criarAdministrador(
                    tenantId: $tenant->id,
                    empresaId: $empresa->id,
                    nome: $dto->adminName,
                    email: $dto->adminEmail,
                    senha: $dto->adminPassword,
                );
            }

            tenancy()->end();

            return [
                'tenant' => $tenant,
                'empresa' => $empresa,
                'admin_user' => $adminUser,
            ];

        } catch (\Exception $e) {
            tenancy()->end();
            
            Log::error('Erro ao criar empresa/usuário no tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new DomainException('Erro ao criar empresa ou usuário administrador: ' . $e->getMessage());
        }
    }
}

