<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Entities\Tenant;
use App\Services\AdminTenancyRunner;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 DDD: Domain Service para buscar empresas no contexto admin
 * Encapsula lógica de tenancy para buscar empresas
 */
class EmpresaAdminService
{
    public function __construct(
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Busca empresas de um tenant (com remoção de duplicatas)
     * 
     * @param Tenant $tenant
     * @return array Array de empresas formatadas
     */
    public function buscarEmpresasDoTenant(Tenant $tenant): array
    {
        return $this->adminTenancyRunner->runForTenant($tenant, function () {
            // Buscar empresas do tenant atual usando Eloquent (respeita tenancy)
            $empresas = Empresa::select('id', 'razao_social', 'cnpj', 'status')
                ->orderBy('razao_social')
                ->get();

            // Remover duplicatas baseado no ID
            $empresasUnicas = [];
            $idsProcessados = [];

            foreach ($empresas as $empresa) {
                $empresaId = (int) $empresa->id;
                if (!in_array($empresaId, $idsProcessados)) {
                    $empresasUnicas[] = [
                        'id' => $empresaId,
                        'razao_social' => $empresa->razao_social,
                        'cnpj' => $empresa->cnpj,
                        'status' => $empresa->status,
                    ];
                    $idsProcessados[] = $empresaId;
                }
            }

            return $empresasUnicas;
        });
    }
}

