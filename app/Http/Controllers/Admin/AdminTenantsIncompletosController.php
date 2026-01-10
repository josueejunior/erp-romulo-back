<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Tenant\UseCases\ListarTenantsIncompletosUseCase;
use App\Application\Tenant\UseCases\DeletarTenantIncompletoUseCase;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller Admin para gerenciar tenants incompletos/abandonados
 * 
 * Permite visualizar e limpar tenants que ficaram incompletos
 * por cadastros abandonados ou falhas no processo.
 */
class AdminTenantsIncompletosController extends Controller
{
    public function __construct(
        private readonly ListarTenantsIncompletosUseCase $listarTenantsIncompletosUseCase,
        private readonly DeletarTenantIncompletoUseCase $deletarTenantIncompletoUseCase,
    ) {}

    /**
     * Lista todos os tenants incompletos
     * 
     * GET /api/admin/tenants-incompletos
     */
    public function index(): JsonResponse
    {
        try {
            Log::info('AdminTenantsIncompletosController::index - Listando tenants incompletos');
            
            $tenants = $this->listarTenantsIncompletosUseCase->executar();
            
            // Agrupar por motivo para estatísticas
            $estatisticas = [
                'total' => count($tenants),
                'por_motivo' => [
                    'sem_empresas' => collect($tenants)->where('diagnostico.motivo', 'sem_empresas')->count(),
                    'empresas_sem_razao_social' => collect($tenants)->where('diagnostico.motivo', 'empresas_sem_razao_social')->count(),
                    'todas_empresas_inativas' => collect($tenants)->where('diagnostico.motivo', 'todas_empresas_inativas')->count(),
                    'erro_inicializacao' => collect($tenants)->where('diagnostico.motivo', 'erro_inicializacao')->count(),
                ],
            ];
            
            return ApiResponse::success(
                'Tenants incompletos listados com sucesso.',
                [
                    'tenants' => $tenants,
                    'estatisticas' => $estatisticas,
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('AdminTenantsIncompletosController::index - Erro', [
                'error' => $e->getMessage(),
            ]);
            
            return ApiResponse::error('Erro ao listar tenants incompletos.', 500);
        }
    }

    /**
     * Deleta um tenant incompleto específico
     * 
     * DELETE /api/admin/tenants-incompletos/{tenantId}
     */
    public function destroy(int $tenantId): JsonResponse
    {
        try {
            Log::info('AdminTenantsIncompletosController::destroy - Deletando tenant', [
                'tenant_id' => $tenantId,
            ]);
            
            $resultado = $this->deletarTenantIncompletoUseCase->executar($tenantId);
            
            return ApiResponse::success(
                $resultado['message'],
                $resultado['tenant']
            );
            
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
            
        } catch (\Exception $e) {
            Log::error('AdminTenantsIncompletosController::destroy - Erro', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            return ApiResponse::error('Erro ao deletar tenant.', 500);
        }
    }

    /**
     * Deleta múltiplos tenants incompletos
     * 
     * POST /api/admin/tenants-incompletos/deletar-lote
     */
    public function deletarLote(Request $request): JsonResponse
    {
        try {
            $tenantIds = $request->input('tenant_ids', []);
            
            if (empty($tenantIds)) {
                return ApiResponse::error('Nenhum tenant selecionado.', 400);
            }
            
            Log::info('AdminTenantsIncompletosController::deletarLote - Deletando tenants em lote', [
                'total' => count($tenantIds),
            ]);
            
            $resultado = $this->deletarTenantIncompletoUseCase->executarEmLote($tenantIds);
            
            $mensagem = sprintf(
                '%d tenant(s) deletado(s) com sucesso. %d falha(s).',
                count($resultado['sucesso']),
                count($resultado['falha'])
            );
            
            return ApiResponse::success($mensagem, $resultado);
            
        } catch (\Exception $e) {
            Log::error('AdminTenantsIncompletosController::deletarLote - Erro', [
                'error' => $e->getMessage(),
            ]);
            
            return ApiResponse::error('Erro ao deletar tenants.', 500);
        }
    }
}

