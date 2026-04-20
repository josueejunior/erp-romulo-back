<?php

namespace App\Application\Tenant\UseCases;

use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Jobs\SetupTenantJob;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Criar Tenant com Empresa e opcionalmente Usuário Admin
 * 
 * 🔥 REFATORADO: Agora usa Job assíncrono para processamento
 * O processo pesado (criação de banco, migrations, etc) acontece em background
 */
class CriarTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantDatabaseServiceInterface $databaseService,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * Agora apenas cria o registro do tenant com status 'pending'
     * e dispara o Job para processamento assíncrono
     */
    public function executar(CriarTenantDTO $dto, bool $requireAdmin = false): array
    {
        // Validar se admin é obrigatório
        if ($requireAdmin && !$dto->temDadosAdmin()) {
            throw new DomainException('Dados do administrador são obrigatórios.');
        }

        // Criar entidade Tenant com status 'pending'
        $tenant = new Tenant(
            id: null,
            razaoSocial: $dto->razaoSocial,
            cnpj: $dto->cnpj,
            email: $dto->email,
            status: 'pending', // 🔥 NOVO: Status inicial é 'pending'
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

        // 🔥 CORREÇÃO: Encontrar próximo ID disponível antes de criar
        // Isso evita conflitos com bancos de dados já existentes
        $proximoIdDisponivel = $this->databaseService->encontrarProximoNumeroDisponivel();
        
        Log::info('CriarTenantUseCase - Próximo ID disponível encontrado', [
            'proximo_id' => $proximoIdDisponivel,
        ]);

        // Persistir tenant (infraestrutura) com ID específico para evitar conflitos
        $tenant = $this->tenantRepository->criarComId($tenant, $proximoIdDisponivel);

        Log::info('CriarTenantUseCase - Tenant criado, disparando Job assíncrono', [
            'tenant_id' => $tenant->id,
            'status' => $tenant->status,
        ]);

        // 🔥 NOVO: Disparar Job para processamento assíncrono
        SetupTenantJob::dispatch($tenant->id, $dto->toArray());

        return [
            'tenant' => $tenant,
            'status' => 'pending',
            'message' => 'Empresa criada e processamento iniciado em background.',
        ];
    }
}
