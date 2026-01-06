<?php

namespace App\Modules\Assinatura\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Plano\UseCases\ListarPlanosUseCase;
use App\Application\Plano\UseCases\BuscarPlanoUseCase;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciamento de Planos
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Use Cases para lógica de negócio
 * - Usa Repository para acesso a dados
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 * 
 * Rotas públicas - podem ser visualizadas sem autenticação
 * Não requer tenant ou empresa (planos são globais)
 * 
 * Segue o mesmo padrão do OrgaoController:
 * - Não precisa de tenant_id (planos são globais)
 * - Não precisa de empresa_id (planos são globais)
 */
class PlanoController extends BaseApiController
{
    public function __construct(
        private ListarPlanosUseCase $listarPlanosUseCase,
        private BuscarPlanoUseCase $buscarPlanoUseCase,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Lista todos os planos ativos
     * Retorna entidades de domínio transformadas
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Aplica filtros opcionais
     * - Chama Use Case para listar
     * - Transforma entidades em arrays
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id (planos são globais)
     * - Não acessa Tenant (planos são globais)
     * - Não filtra por empresa (planos são globais)
     * 
     * GET /api/v1/planos
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filtros = [];
            
            // Aplicar filtros se necessário
            if ($request->has('ativo')) {
                $filtros['ativo'] = $request->boolean('ativo');
            }

            // Executar Use Case (retorna entidades de domínio)
            $planosDomain = $this->listarPlanosUseCase->executar($filtros);

            // Converter entidades de domínio para modelos Eloquent para manter compatibilidade com frontend
            $planos = $planosDomain->map(function ($planoDomain) {
                return $this->planoRepository->buscarModeloPorId($planoDomain->id);
            })->filter(); // Remove nulls

            return response()->json([
                'data' => $planos->values()->all(),
                'meta' => [
                    'total' => $planos->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar planos');
        }
    }

    /**
     * Busca um plano específico por ID
     * Retorna entidade de domínio transformada
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Chama Use Case para buscar
     * - Transforma entidade em array
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id (planos são globais)
     * - Não acessa Tenant (planos são globais)
     * - Não filtra por empresa (planos são globais)
     * 
     * GET /api/v1/planos/{plano}
     */
    public function get(Request $request, int $plano): JsonResponse
    {
        try {
            // Executar Use Case
            $planoDomain = $this->buscarPlanoUseCase->executar($plano);
            
            // Buscar modelo Eloquent para resposta (mantém compatibilidade com frontend)
            $planoModel = $this->planoRepository->buscarModeloPorId($planoDomain->id);

            if (!$planoModel) {
                return response()->json([
                    'message' => 'Plano não encontrado'
                ], 404);
            }

            return response()->json([
                'data' => $planoModel
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar plano');
        }
    }
}

