<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Use Case: Buscar Tenant por ID
 */
class BuscarTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $id ID do tenant
     * @return array Dados do tenant formatados
     */
    public function executar(int $id): array
    {
        $tenant = $this->tenantRepository->buscarPorId($id);

        if (!$tenant) {
            throw new NotFoundException('Tenant não encontrado.');
        }

        return [
            'id' => $tenant->id,
            'razao_social' => $tenant->razaoSocial,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'telefone' => $tenant->telefone,
            'status' => $tenant->status,
            'plano_atual_id' => $tenant->planoAtualId,
            'assinatura_atual_id' => $tenant->assinaturaAtualId,
            'criado_em' => $tenant->criadoEm?->format('Y-m-d H:i:s'),
            'atualizado_em' => $tenant->atualizadoEm?->format('Y-m-d H:i:s'),
        ];
    }
}

