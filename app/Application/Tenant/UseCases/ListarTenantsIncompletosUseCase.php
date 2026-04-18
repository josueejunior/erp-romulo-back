<?php

declare(strict_types=1);

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Use Case: Listar Tenants Incompletos/Abandonados
 * 
 * Um tenant é considerado incompleto se:
 * - Não tem nenhuma empresa cadastrada
 * - Não tem nenhuma empresa com razao_social preenchida
 * - Todas as empresas estão inativas
 * 
 * Esses tenants podem ser resultado de cadastros abandonados ou falhas no processo.
 */
final class ListarTenantsIncompletosUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Lista todos os tenants incompletos
     * 
     * @return array Lista de tenants incompletos com informações de diagnóstico
     */
    public function executar(): array
    {
        Log::info('ListarTenantsIncompletosUseCase::executar - Iniciando busca de tenants incompletos');
        
        $tenantsIncompletos = [];
        
        try {
            // Buscar todos os tenants
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
            $tenants = $tenantsPaginator->getCollection();
            
            Log::debug('ListarTenantsIncompletosUseCase::executar - Total de tenants para analisar', [
                'total' => $tenants->count(),
            ]);
            
            foreach ($tenants as $tenantDomain) {
                try {
                    $diagnostico = $this->analisarTenant($tenantDomain);
                    
                    if (!$diagnostico['completo']) {
                        $tenantsIncompletos[] = [
                            'id' => $tenantDomain->id,
                            'razao_social' => $tenantDomain->razaoSocial,
                            'cnpj' => $tenantDomain->cnpj,
                            'email' => $tenantDomain->email,
                            'status' => $tenantDomain->status,
                            'created_at' => $tenantDomain->createdAt ?? null,
                            'diagnostico' => $diagnostico,
                        ];
                    }
                } catch (\Exception $e) {
                    // Tenants com erro de inicialização são considerados incompletos
                    $tenantsIncompletos[] = [
                        'id' => $tenantDomain->id,
                        'razao_social' => $tenantDomain->razaoSocial,
                        'cnpj' => $tenantDomain->cnpj,
                        'email' => $tenantDomain->email,
                        'status' => $tenantDomain->status,
                        'created_at' => $tenantDomain->createdAt ?? null,
                        'diagnostico' => [
                            'completo' => false,
                            'motivo' => 'erro_inicializacao',
                            'erro' => $e->getMessage(),
                            'total_empresas' => 0,
                            'empresas_ativas' => 0,
                            'empresas_completas' => 0,
                            'total_usuarios' => 0,
                            'usuarios_ativos' => 0,
                        ],
                    ];
                    Log::warning('ListarTenantsIncompletosUseCase::executar - Erro ao analisar tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            Log::info('ListarTenantsIncompletosUseCase::executar - Análise concluída', [
                'total_analisados' => $tenants->count(),
                'total_incompletos' => count($tenantsIncompletos),
            ]);
            
            return $tenantsIncompletos;
            
        } catch (\Exception $e) {
            Log::error('ListarTenantsIncompletosUseCase::executar - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Analisa um tenant e retorna diagnóstico
     */
    private function analisarTenant($tenantDomain): array
    {
        // Buscar modelo Eloquent para inicializar tenancy
        $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
        if (!$tenant) {
            return [
                'completo' => false,
                'motivo' => 'tenant_nao_encontrado',
                'total_empresas' => 0,
                'empresas_ativas' => 0,
                'empresas_completas' => 0,
                'total_usuarios' => 0,
                'usuarios_ativos' => 0,
            ];
        }
        
        tenancy()->initialize($tenant);
        
        try {
            // Buscar empresas
            $empresas = $this->empresaRepository->listar();
            $totalEmpresas = count($empresas);
            $empresasAtivas = 0;
            $empresasCompletas = 0;
            
            foreach ($empresas as $empresa) {
                if ($empresa->estaAtiva()) {
                    $empresasAtivas++;
                }
                if (!empty($empresa->razaoSocial) && trim($empresa->razaoSocial) !== '') {
                    $empresasCompletas++;
                }
            }
            
            // Buscar usuários
            $totalUsuarios = \App\Modules\Auth\Models\User::withTrashed()->count();
            $usuariosAtivos = \App\Modules\Auth\Models\User::count();
            
            tenancy()->end();
            
            // Determinar se está completo
            $completo = $empresasAtivas > 0 && $empresasCompletas > 0;
            
            $motivo = null;
            if (!$completo) {
                if ($totalEmpresas === 0) {
                    $motivo = 'sem_empresas';
                } elseif ($empresasCompletas === 0) {
                    $motivo = 'empresas_sem_razao_social';
                } elseif ($empresasAtivas === 0) {
                    $motivo = 'todas_empresas_inativas';
                } else {
                    $motivo = 'desconhecido';
                }
            }
            
            return [
                'completo' => $completo,
                'motivo' => $motivo,
                'total_empresas' => $totalEmpresas,
                'empresas_ativas' => $empresasAtivas,
                'empresas_completas' => $empresasCompletas,
                'total_usuarios' => $totalUsuarios,
                'usuarios_ativos' => $usuariosAtivos,
            ];
            
        } catch (\Exception $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            throw $e;
        }
    }
}


