<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Empresa\Entities\Empresa as EmpresaDomain;
use App\Services\AdminTenancyRunner;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 DDD: Domain Service para buscar empresas no contexto admin
 * 
 * Princípios DDD aplicados:
 * - Usa Repository Interface (abstração de persistência)
 * - Trabalha com entidades de domínio (não modelos Eloquent)
 * - Encapsula lógica de negócio (remoção de duplicatas por CNPJ)
 * - Não conhece detalhes de implementação (Eloquent, MySQL, etc)
 */
class EmpresaAdminService
{
    public function __construct(
        private AdminTenancyRunner $adminTenancyRunner,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Busca empresas de um tenant (com remoção de duplicatas por CNPJ normalizado)
     * 
     * 🔥 DDD: Domain Service - encapsula regra de negócio de remover duplicatas
     * 
     * @param Tenant $tenant
     * @return array Array de empresas formatadas (sem duplicatas por CNPJ)
     */
    public function buscarEmpresasDoTenant(Tenant $tenant): array
    {
        // Capturar tenant_id antes da closure para usar no log
        $tenantId = $tenant->id;
        
        // 🔥 DDD: Usar AdminTenancyRunner para isolar tenancy
        return $this->adminTenancyRunner->runForTenant($tenant, function () use ($tenantId) {
            // 🔥 DDD: Usar Repository Interface ao invés de Eloquent diretamente
            $empresasDomain = $this->empresaRepository->listar();
            
            // Filtrar apenas empresas ativas (regra de negócio)
            $empresasAtivas = array_filter($empresasDomain, function (EmpresaDomain $empresa) {
                return $empresa->status === 'ativa';
            });
            
            // Ordenar por razão social (regra de apresentação)
            usort($empresasAtivas, function (EmpresaDomain $a, EmpresaDomain $b) {
                return strcmp($a->razaoSocial, $b->razaoSocial);
            });

            // 🔥 DDD: Remover duplicatas baseado no CNPJ normalizado (regra de negócio)
            $empresasUnicas = [];
            $cnpjsProcessados = [];
            $idsProcessados = [];

            foreach ($empresasAtivas as $empresa) {
                $empresaId = $empresa->id;
                
                // Normalizar CNPJ (remover formatação) para comparação
                $cnpjNormalizado = $empresa->cnpj ? preg_replace('/[^0-9]/', '', $empresa->cnpj) : null;
                
                // Se tem CNPJ válido, verificar duplicata por CNPJ normalizado
                if ($cnpjNormalizado && strlen($cnpjNormalizado) === 14) {
                    if (in_array($cnpjNormalizado, $cnpjsProcessados)) {
                        // Empresa com CNPJ duplicado - pular (manter a primeira encontrada)
                        Log::warning('Empresa duplicada por CNPJ ignorada', [
                            'empresa_id' => $empresaId,
                            'cnpj' => $empresa->cnpj,
                            'cnpj_normalizado' => $cnpjNormalizado,
                            'razao_social' => $empresa->razaoSocial,
                            'tenant_id' => $tenantId,
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
                $cnpjFormatado = $this->formatarCnpj($cnpjNormalizado) ?? $empresa->cnpj;
                
                // Converter entidade de domínio para array (DTO para camada de apresentação)
                $empresasUnicas[] = [
                    'id' => $empresaId,
                    'razao_social' => $empresa->razaoSocial,
                    'cnpj' => $cnpjFormatado,
                    'cnpj_normalizado' => $cnpjNormalizado, // CNPJ sem formatação para comparações
                    'status' => $empresa->status,
                ];
                $idsProcessados[] = $empresaId;
            }

            Log::debug('Empresas do tenant carregadas (após remoção de duplicatas)', [
                'tenant_id' => $tenantId,
                'total_buscadas' => count($empresasAtivas),
                'total_unicas' => count($empresasUnicas),
                'cnpjs_unicos' => count($cnpjsProcessados),
            ]);

            return $empresasUnicas;
        });
    }

    /**
     * Formatar CNPJ para exibição (XX.XXX.XXX/XXXX-XX)
     * 
     * @param string|null $cnpjNormalizado CNPJ sem formatação (apenas números)
     * @return string|null CNPJ formatado ou null se inválido
     */
    private function formatarCnpj(?string $cnpjNormalizado): ?string
    {
        if (!$cnpjNormalizado || strlen($cnpjNormalizado) !== 14) {
            return null;
        }
        
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpjNormalizado, 0, 2),
            substr($cnpjNormalizado, 2, 3),
            substr($cnpjNormalizado, 5, 3),
            substr($cnpjNormalizado, 8, 4),
            substr($cnpjNormalizado, 12, 2)
        );
    }
}





