<?php

namespace App\Modules\Assinatura\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Plano\UseCases\ListarPlanosUseCase;
use App\Application\Plano\UseCases\BuscarPlanoUseCase;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
        Log::info('🔍 PlanoController::list - Iniciando listagem de planos', [
            'path' => $request->path(),
            'method' => $request->method(),
            'query_params' => $request->query(),
            'headers' => $request->headers->all(),
        ]);

        try {
            // Preparar filtros
            $filtros = [];
            
            // Aplicar filtros se necessário
            if ($request->has('ativo')) {
                $filtros['ativo'] = $request->boolean('ativo');
            }

            Log::info('🔍 PlanoController::list - Filtros preparados', [
                'filtros' => $filtros,
            ]);

            // Executar Use Case (retorna entidades de domínio)
            Log::info('🔍 PlanoController::list - Chamando ListarPlanosUseCase::executar');
            $planosDomain = $this->listarPlanosUseCase->executar($filtros);

            Log::info('🔍 PlanoController::list - UseCase retornou planos domain', [
                'count' => $planosDomain->count(),
                'ids' => $planosDomain->pluck('id')->toArray(),
            ]);

            // Converter entidades de domínio para modelos Eloquent para manter compatibilidade com frontend
            Log::info('🔍 PlanoController::list - Convertendo entidades para modelos Eloquent');
            $planos = $planosDomain->map(function ($planoDomain) {
                $modelo = $this->planoRepository->buscarModeloPorId($planoDomain->id);
                Log::debug('🔍 PlanoController::list - Convertendo plano', [
                    'domain_id' => $planoDomain->id,
                    'modelo_encontrado' => $modelo !== null,
                ]);
                return $modelo;
            })->filter(); // Remove nulls

            Log::info('🔍 PlanoController::list - Conversão concluída', [
                'planos_count' => $planos->count(),
                'planos_ids' => $planos->pluck('id')->toArray(),
            ]);

            $response = [
                'data' => $planos->values()->all(),
                'meta' => [
                    'total' => $planos->count(),
                ],
            ];

            Log::info('✅ PlanoController::list - Retornando resposta', [
                'total' => $planos->count(),
                'response_keys' => array_keys($response),
            ]);

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('❌ PlanoController::list - Erro ao listar planos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
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

