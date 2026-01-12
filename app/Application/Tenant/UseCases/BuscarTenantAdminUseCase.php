<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\EmpresaFinder;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para buscar tenant no admin
 * Encapsula lógica de busca com empresa principal
 */
class BuscarTenantAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private EmpresaFinder $empresaFinder,
    ) {}

    /**
     * Busca tenant por ID com dados da empresa principal
     * 
     * @param int $tenantId
     * @return array
     * @throws DomainException
     */
    public function executar(int $tenantId): array
    {
        $tenant = $this->tenantRepository->buscarPorId($tenantId);

        if (!$tenant) {
            throw new DomainException('Empresa não encontrada.');
        }

        // Buscar empresa principal usando Domain Service
        $empresa = $this->empresaFinder->findPrincipalByTenantId($tenantId);

        // Montar dados completos
        return [
            'id' => $tenant->id,
            'razao_social' => $tenant->razaoSocial,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'status' => $tenant->status,
            'endereco' => $tenant->endereco,
            'cidade' => $tenant->cidade,
            'estado' => $tenant->estado,
            'cep' => $tenant->cep,
            'telefones' => $tenant->telefones,
            'emails_adicionais' => $tenant->emailsAdicionais,
            'banco' => $tenant->banco,
            'agencia' => $tenant->agencia,
            'conta' => $tenant->conta,
            'tipo_conta' => $tenant->tipoConta,
            'pix' => $tenant->pix,
            'representante_legal_nome' => $tenant->representanteLegalNome,
            'representante_legal_cpf' => $tenant->representanteLegalCpf,
            'representante_legal_cargo' => $tenant->representanteLegalCargo,
            'logo' => $tenant->logo,
            'empresa_id' => $empresa['id'],
            'empresa_razao_social' => $empresa['razao_social'],
            'empresa_cnpj' => $empresa['cnpj'],
        ];
    }
}


