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

        // Verificar se já existe tenant com mesmo CNPJ antes de criar
        if ($dto->cnpj) {
            $tenantExistente = $this->tenantRepository->buscarPorCnpj($dto->cnpj);
            if ($tenantExistente) {
                throw new DomainException("Já existe uma empresa cadastrada com o CNPJ informado. ID: {$tenantExistente->id}");
            }
        }

        // Persistir tenant (infraestrutura) - primeira tentativa com ID automático
        $tenant = $this->tenantRepository->criar($tenant);

        try {
            // Criar banco de dados do tenant (infraestrutura)
            $this->databaseService->criarBancoDados($tenant);
            // Executar migrations
            $this->databaseService->executarMigrations($tenant);
        } catch (\App\Domain\Exceptions\DatabaseAlreadyExistsException $e) {
            // 🔥 NOVO: Banco já existe, criar tenant com próximo número disponível
            Log::info('Banco já existe, recriando tenant com próximo número disponível', [
                'tenant_id_anterior' => $tenant->id,
                'proximo_numero' => $e->proximoNumeroDisponivel,
            ]);
            
            // Deletar tenant criado anteriormente
            try {
                $this->tenantRepository->deletar($tenant->id);
            } catch (\Exception $deleteException) {
                Log::warning('Erro ao deletar tenant anterior', [
                    'tenant_id' => $tenant->id,
                    'error' => $deleteException->getMessage(),
                ]);
            }
            
            // Tentar criar com o próximo número disponível
            // Se falhar (porque o número já existe), tentar novamente com próximo
            $tentativas = 0;
            $maxTentativas = 5;
            $proximoNumero = $e->proximoNumeroDisponivel;
            $tenant = null;
            
            while ($tentativas < $maxTentativas && !$tenant) {
                $tentativas++;
                
                try {
                    // Verificar se o número já existe antes de tentar criar
                    $tenantExistente = $this->tenantRepository->buscarPorId($proximoNumero);
                    if ($tenantExistente) {
                        Log::warning('Próximo número já existe como tenant, tentando próximo', [
                            'numero_tentado' => $proximoNumero,
                            'tentativa' => $tentativas,
                        ]);
                        $proximoNumero++;
                        continue;
                    }
                    
                    // Criar novo tenant com ID específico (próximo número disponível)
                    $tenant = $this->tenantRepository->criarComId(
                        new Tenant(
                            id: null,
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
                        ),
                        $proximoNumero
                    );
                    
                    // Se chegou aqui, sucesso!
                    break;
                    
                } catch (\RuntimeException $runtimeException) {
                    // Se o erro é porque o ID já existe, tentar próximo
                    if (str_contains($runtimeException->getMessage(), 'Já existe um tenant com ID')) {
                        Log::warning('ID já existe, tentando próximo', [
                            'numero_tentado' => $proximoNumero,
                            'tentativa' => $tentativas,
                        ]);
                        $proximoNumero++;
                        continue;
                    }
                    // Outros erros: relançar
                    throw $runtimeException;
                } catch (\Exception $createException) {
                    Log::error('Erro ao criar tenant com ID específico', [
                        'numero_tentado' => $proximoNumero,
                        'tentativa' => $tentativas,
                        'error' => $createException->getMessage(),
                    ]);
                    throw $createException;
                }
            }
            
            // Verificar se conseguiu criar o tenant
            if (!$tenant) {
                throw new DomainException("Não foi possível criar tenant após {$maxTentativas} tentativas. Todos os números disponíveis já estão em uso.");
            }
            
            // Tentar criar banco novamente com o novo ID
            try {
                $this->databaseService->criarBancoDados($tenant);
                $this->databaseService->executarMigrations($tenant);
            } catch (\Exception $retryException) {
                // Se ainda falhar, deletar o tenant e lançar erro
                try {
                    $this->tenantRepository->deletar($tenant->id);
                } catch (\Exception $deleteException) {
                    Log::warning('Erro ao deletar tenant após falha na segunda tentativa', [
                        'tenant_id' => $tenant->id,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
                
                throw new DomainException('Erro ao criar o banco de dados da empresa após tentar com próximo número disponível: ' . $retryException->getMessage());
            }
            
        } catch (\Exception $e) {
            // Outros erros
            // Se falhar, deletar o tenant criado
            if ($tenant && $tenant->id) {
                try {
                    $this->tenantRepository->deletar($tenant->id);
                } catch (\Exception $deleteException) {
                    Log::warning('Erro ao deletar tenant após falha na criação do banco', [
                        'tenant_id' => $tenant->id,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
            }
            
            // Melhorar mensagem de erro
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'already exists') || 
                (str_contains($errorMessage, 'Database') && str_contains($errorMessage, 'exists'))) {
                throw new DomainException("Erro ao criar banco de dados: O banco de dados 'tenant_{$tenant->id}' já existe. Isso pode acontecer se uma tentativa anterior de criação falhou. Por favor, delete o banco de dados manualmente ou entre em contato com o suporte técnico.");
            }
            
            throw new DomainException('Erro ao criar o banco de dados da empresa: ' . $errorMessage);
        }

        // Inicializar contexto do tenant
        // 🔥 ARQUITETURA LIMPA: Usar TenantRepository em vez de Eloquent direto
        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenant->id);
        if (!$tenantModel) {
            throw new DomainException('Erro ao buscar tenant criado.');
        }
        tenancy()->initialize($tenantModel);

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
            
            Log::error('Erro ao criar empresa/usuário no tenant - iniciando rollback', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // 🔥 ROLLBACK: Se houver erro na criação da empresa/usuário, deletar tenant criado
            // Isso garante que não fiquem tenants órfãos no sistema
            // Nota: O banco de dados do tenant pode ficar órfão temporariamente, mas será detectado e limpo depois
            try {
                Log::info('CriarTenantUseCase::executar - Deletando tenant após erro na criação da empresa/usuário', [
                    'tenant_id' => $tenant->id,
                ]);
                
                // Deletar tenant do banco central (o banco de dados pode ficar órfão temporariamente)
                // Para deletar o banco, seria necessário usar o DeletarTenantIncompletoUseCase, mas isso criaria dependência circular
                // O banco órfão será detectado e limpo pelos processos de manutenção
                $this->tenantRepository->deletar($tenant->id);
                
                Log::info('CriarTenantUseCase::executar - Tenant deletado com sucesso após erro', [
                    'tenant_id' => $tenant->id,
                    'note' => 'Banco de dados do tenant pode ter ficado órfão e será limpo depois',
                ]);
            } catch (\Exception $rollbackException) {
                Log::error('CriarTenantUseCase::executar - Erro ao fazer rollback (deletar tenant)', [
                    'tenant_id' => $tenant->id,
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                    'trace' => $rollbackException->getTraceAsString(),
                ]);
                // Continuar mesmo se falhar o rollback - o tenant ficará órfão mas será detectado depois
            }
            
            throw new DomainException('Erro ao criar empresa ou usuário administrador: ' . $e->getMessage());
        }
    }
}

