<?php

namespace App\Domain\Tenant\Services;

use App\Models\TenantEmpresa;
use App\Models\Tenant;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * 🔥 DDD: Domain Service para buscar empresa principal de um tenant
 * Encapsula regra de negócio: "Como encontrar a empresa principal de um tenant?"
 * 
 * Regras:
 * 1. Tentar buscar via mapeamento TenantEmpresa (cache/mapeamento direto)
 * 2. Fallback: buscar primeira empresa do tenant (se não houver mapeamento)
 * 3. Criar mapeamento para próxima vez (cache)
 */
class EmpresaFinder
{
    /**
     * Busca empresa principal de um tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array{id: int|null, razao_social: string|null, cnpj: string|null}
     */
    public function findPrincipalByTenantId(int $tenantId): array
    {
        try {
            // 1. Tentar buscar via mapeamento TenantEmpresa (mais rápido)
            $empresaIdMapeado = TenantEmpresa::findEmpresaIdByTenantId($tenantId);
            
            if ($empresaIdMapeado) {
                $empresa = $this->buscarEmpresaNoTenant($tenantId, $empresaIdMapeado);
                
                if ($empresa) {
                    return [
                        'id' => $empresa['id'],
                        'razao_social' => $empresa['razao_social'],
                        'cnpj' => $empresa['cnpj'],
                    ];
                }
            }

            // 2. Fallback: buscar primeira empresa do tenant
            $empresa = $this->buscarPrimeiraEmpresaDoTenant($tenantId);
            
            if ($empresa) {
                // 3. Criar mapeamento para próxima vez (cache)
                try {
                    TenantEmpresa::createOrUpdateMapping($tenantId, $empresa['id']);
                } catch (\Exception $e) {
                    Log::warning('EmpresaFinder: Erro ao criar mapeamento TenantEmpresa', [
                        'tenant_id' => $tenantId,
                        'empresa_id' => $empresa['id'],
                        'error' => $e->getMessage(),
                    ]);
                }

                return $empresa;
            }

            return [
                'id' => null,
                'razao_social' => null,
                'cnpj' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('EmpresaFinder: Erro ao buscar empresa principal', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => null,
                'razao_social' => null,
                'cnpj' => null,
            ];
        }
    }

    /**
     * Busca empresa específica dentro do tenant
     */
    private function buscarEmpresaNoTenant(int $tenantId, int $empresaId): ?array
    {
        $tenantModel = Tenant::find($tenantId);
        if (!$tenantModel) {
            return null;
        }

        try {
            Tenancy::initialize($tenantModel);
            
            try {
                $empresa = Empresa::find($empresaId);
                
                if ($empresa) {
                    return [
                        'id' => $empresa->id,
                        'razao_social' => $empresa->razao_social,
                        'cnpj' => $empresa->cnpj,
                    ];
                }
            } finally {
                Tenancy::end();
            }
        } catch (\Exception $e) {
            Log::warning('EmpresaFinder: Erro ao buscar empresa no tenant', [
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
            
            if (Tenancy::initialized) {
                Tenancy::end();
            }
        }

        return null;
    }

    /**
     * Busca primeira empresa do tenant (fallback)
     */
    private function buscarPrimeiraEmpresaDoTenant(int $tenantId): ?array
    {
        $tenantModel = Tenant::find($tenantId);
        if (!$tenantModel) {
            return null;
        }

        try {
            Tenancy::initialize($tenantModel);
            
            try {
                $empresa = Empresa::first();
                
                if ($empresa) {
                    return [
                        'id' => $empresa->id,
                        'razao_social' => $empresa->razao_social,
                        'cnpj' => $empresa->cnpj,
                    ];
                }
            } finally {
                Tenancy::end();
            }
        } catch (\Exception $e) {
            Log::warning('EmpresaFinder: Erro ao buscar primeira empresa do tenant', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            if (Tenancy::initialized) {
                Tenancy::end();
            }
        }

        return null;
    }
}

