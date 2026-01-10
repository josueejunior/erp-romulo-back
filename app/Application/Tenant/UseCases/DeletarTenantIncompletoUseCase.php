<?php

declare(strict_types=1);

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Deletar Tenant Incompleto/Abandonado
 * 
 * Remove completamente um tenant que está incompleto.
 * ATENÇÃO: Esta operação é IRREVERSÍVEL e deleta:
 * - O banco de dados do tenant
 * - Todos os registros do tenant na tabela central
 */
final class DeletarTenantIncompletoUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly ListarTenantsIncompletosUseCase $listarTenantsIncompletosUseCase,
    ) {}

    /**
     * Deleta um tenant incompleto
     * 
     * @param int $tenantId ID do tenant a ser deletado
     * @param bool $forcar Se true, deleta mesmo se tiver dados (PERIGOSO)
     * @return array Resultado da operação
     * @throws DomainException Se tenant não for incompleto ou não puder ser deletado
     */
    public function executar(int $tenantId, bool $forcar = false): array
    {
        Log::info('DeletarTenantIncompletoUseCase::executar - Iniciando deleção', [
            'tenant_id' => $tenantId,
            'forcar' => $forcar,
        ]);
        
        // Verificar se tenant existe
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenantDomain) {
            throw new DomainException('Tenant não encontrado.');
        }
        
        // Verificar se é realmente incompleto (a menos que force)
        if (!$forcar) {
            $tenantsIncompletos = $this->listarTenantsIncompletosUseCase->executar();
            $tenantIncompleto = collect($tenantsIncompletos)->firstWhere('id', $tenantId);
            
            if (!$tenantIncompleto) {
                throw new DomainException(
                    'Este tenant não pode ser deletado pois possui dados válidos. ' .
                    'Tenants com empresas ativas não podem ser removidos por segurança.'
                );
            }
        }
        
        try {
            return DB::transaction(function () use ($tenantId, $tenantDomain) {
                $tenant = $this->tenantRepository->buscarModeloPorId($tenantId);
                
                if (!$tenant) {
                    throw new DomainException('Modelo do tenant não encontrado.');
                }
                
                // Guardar informações para log
                $info = [
                    'tenant_id' => $tenantId,
                    'razao_social' => $tenantDomain->razaoSocial,
                    'cnpj' => $tenantDomain->cnpj,
                ];
                
                // Deletar o tenant (isso também deleta o banco de dados se configurado)
                $tenant->delete();
                
                Log::info('DeletarTenantIncompletoUseCase::executar - Tenant deletado com sucesso', $info);
                
                return [
                    'success' => true,
                    'message' => 'Tenant deletado com sucesso.',
                    'tenant' => $info,
                ];
            });
            
        } catch (DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('DeletarTenantIncompletoUseCase::executar - Erro ao deletar tenant', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DomainException('Erro ao deletar tenant: ' . $e->getMessage());
        }
    }
    
    /**
     * Deleta múltiplos tenants incompletos
     * 
     * @param array $tenantIds IDs dos tenants a serem deletados
     * @return array Resultado da operação com sucesso/falha por tenant
     */
    public function executarEmLote(array $tenantIds): array
    {
        Log::info('DeletarTenantIncompletoUseCase::executarEmLote - Iniciando deleção em lote', [
            'total' => count($tenantIds),
            'tenant_ids' => $tenantIds,
        ]);
        
        $resultados = [
            'sucesso' => [],
            'falha' => [],
        ];
        
        foreach ($tenantIds as $tenantId) {
            try {
                $resultado = $this->executar((int) $tenantId, false);
                $resultados['sucesso'][] = $resultado['tenant'];
            } catch (\Exception $e) {
                $resultados['falha'][] = [
                    'tenant_id' => $tenantId,
                    'erro' => $e->getMessage(),
                ];
            }
        }
        
        Log::info('DeletarTenantIncompletoUseCase::executarEmLote - Concluído', [
            'sucesso' => count($resultados['sucesso']),
            'falha' => count($resultados['falha']),
        ]);
        
        return $resultados;
    }
}

