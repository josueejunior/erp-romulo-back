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
     * Busca empresas de um tenant (com remoção de duplicatas por CNPJ normalizado)
     * 
     * @param Tenant $tenant
     * @return array Array de empresas formatadas (sem duplicatas por CNPJ)
     */
    public function buscarEmpresasDoTenant(Tenant $tenant): array
    {
        return $this->adminTenancyRunner->runForTenant($tenant, function () {
            // Buscar empresas do tenant atual usando Eloquent (respeita tenancy)
            // Filtrar apenas empresas ativas para evitar mostrar empresas inativas
            $empresas = Empresa::select('id', 'razao_social', 'cnpj', 'status')
                ->where('status', 'ativa')
                ->orderBy('razao_social')
                ->get();

            // Remover duplicatas baseado no CNPJ normalizado (sem pontos/barras)
            // Se duas empresas têm o mesmo CNPJ (mesmo que formatado diferente), manter apenas uma
            $empresasUnicas = [];
            $cnpjsProcessados = [];
            $idsProcessados = [];

            foreach ($empresas as $empresa) {
                $empresaId = (int) $empresa->id;
                
                // Normalizar CNPJ (remover formatação) para comparação
                $cnpjNormalizado = $empresa->cnpj ? preg_replace('/[^0-9]/', '', $empresa->cnpj) : null;
                
                // Se tem CNPJ, verificar duplicata por CNPJ normalizado
                if ($cnpjNormalizado && strlen($cnpjNormalizado) === 14) {
                    if (in_array($cnpjNormalizado, $cnpjsProcessados)) {
                        // Empresa com CNPJ duplicado - pular (manter a primeira encontrada)
                        Log::warning('Empresa duplicada por CNPJ ignorada', [
                            'empresa_id' => $empresaId,
                            'cnpj' => $empresa->cnpj,
                            'cnpj_normalizado' => $cnpjNormalizado,
                            'razao_social' => $empresa->razao_social,
                        ]);
                        continue;
                    }
                    $cnpjsProcessados[] = $cnpjNormalizado;
                }
                
                // Evitar duplicatas por ID também (caso não tenha CNPJ)
                if (in_array($empresaId, $idsProcessados)) {
                    continue;
                }
                
                // Formatar CNPJ para exibição (com pontos/barras se válido)
                $cnpjFormatado = $cnpjNormalizado && strlen($cnpjNormalizado) === 14
                    ? sprintf(
                        '%s.%s.%s/%s-%s',
                        substr($cnpjNormalizado, 0, 2),
                        substr($cnpjNormalizado, 2, 3),
                        substr($cnpjNormalizado, 5, 3),
                        substr($cnpjNormalizado, 8, 4),
                        substr($cnpjNormalizado, 12, 2)
                    )
                    : $empresa->cnpj; // Manter formato original se não conseguir normalizar
                
                $empresasUnicas[] = [
                    'id' => $empresaId,
                    'razao_social' => $empresa->razao_social,
                    'cnpj' => $cnpjFormatado,
                    'cnpj_normalizado' => $cnpjNormalizado, // CNPJ sem formatação para comparações
                    'status' => $empresa->status,
                ];
                $idsProcessados[] = $empresaId;
            }

            Log::debug('Empresas do tenant carregadas (após remoção de duplicatas)', [
                'tenant_id' => $tenant->id,
                'total_buscadas' => $empresas->count(),
                'total_unicas' => count($empresasUnicas),
                'cnpjs_unicos' => count($cnpjsProcessados),
            ]);

            return $empresasUnicas;
        });
    }
}

