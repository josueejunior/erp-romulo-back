<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 🔥 DDD: UseCase para listar tenants no admin
 * Encapsula lógica de listagem com relacionamentos
 */
class ListarTenantsAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Lista tenants com relacionamentos (plano, assinatura) e timestamps
     * 
     * @param array $filtros
     * @return LengthAwarePaginator
     */
    public function executar(array $filtros = []): LengthAwarePaginator
    {
        // Buscar tenants do repository (entidades do domínio)
        $tenants = $this->tenantRepository->buscarComFiltros($filtros);

        // Buscar modelos Eloquent com relacionamentos em uma única query
        $tenantIds = $tenants->pluck('id')->toArray();
        
        if (empty($tenantIds)) {
            return $tenants; // Retornar paginator vazio
        }

        // Buscar modelos Eloquent (sem eager loading de assinaturaAtual pois está no banco do tenant)
        // planoAtual está no banco central, então pode ser carregado normalmente
        $tenantModels = Tenant::with(['planoAtual'])
            ->whereIn('id', $tenantIds)
            ->get()
            ->keyBy('id');

        // Converter entidades do domínio para array com dados enriquecidos
        $items = $tenants->getCollection()->map(function ($tenant) use ($tenantModels) {
            return $this->enriquecerTenant($tenant, $tenantModels->get($tenant->id));
        })->values();

        // Criar novo paginator com itens enriquecidos
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $tenants->total(),
            $tenants->perPage(),
            $tenants->currentPage(),
            [
                'path' => $tenants->path(),
                'pageName' => $tenants->getPageName(),
            ]
        );
    }

    /**
     * Enriquece entidade do domínio com dados do modelo Eloquent
     */
    private function enriquecerTenant($tenant, ?Tenant $tenantModel): array
    {
        $data = [
            'id' => $tenant->id,
            'razao_social' => $tenant->razaoSocial,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'status' => $tenant->status,
            'cidade' => $tenant->cidade,
            'estado' => $tenant->estado,
        ];

        // Adicionar timestamps
        if ($tenantModel) {
            $criadoEm = $tenantModel->{$tenantModel->getCreatedAtColumn()};
            $atualizadoEm = $tenantModel->{$tenantModel->getUpdatedAtColumn()};
            
            $data['created_at'] = $criadoEm instanceof \Carbon\Carbon 
                ? $criadoEm->toISOString() 
                : ($criadoEm ?? null);
            $data['updated_at'] = $atualizadoEm instanceof \Carbon\Carbon 
                ? $atualizadoEm->toISOString() 
                : ($atualizadoEm ?? null);
            $data['criado_em'] = $data['created_at'];
            $data['atualizado_em'] = $data['updated_at'];

            // Adicionar relacionamentos
            if ($tenantModel->planoAtual) {
                $data['plano_atual'] = [
                    'id' => $tenantModel->planoAtual->id,
                    'nome' => $tenantModel->planoAtual->nome,
                    'preco_mensal' => $tenantModel->planoAtual->preco_mensal,
                    'preco_anual' => $tenantModel->planoAtual->preco_anual,
                ];
                $data['plano_atual_id'] = $tenantModel->plano_atual_id;
            }
            
            // 🔥 CORREÇÃO: assinaturaAtual está no banco do tenant, não pode ser carregado via eager loading
            // Apenas incluir o ID se existir (cache no tenant)
            if ($tenantModel->assinatura_atual_id) {
                $data['assinatura_atual_id'] = $tenantModel->assinatura_atual_id;
                // Não tentar carregar o relacionamento aqui - está no banco do tenant
                // Se necessário, buscar via AdminTenancyRunner em outro endpoint
            }
        } else {
            $data['created_at'] = null;
            $data['updated_at'] = null;
            $data['criado_em'] = null;
            $data['atualizado_em'] = null;
        }

        return $data;
    }
}

