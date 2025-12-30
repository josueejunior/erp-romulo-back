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
 * Rotas públicas - podem ser visualizadas sem autenticação
 * 
 * Refatorado para usar DDD (Domain-Driven Design)
 * Organizado por módulo seguindo Arquitetura Hexagonal
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
     * 
     * GET /api/v1/planos
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $filtros = [];
            
            // Aplicar filtros se necessário
            if ($request->has('ativo')) {
                $filtros['ativo'] = $request->boolean('ativo');
            }

            // Buscar planos usando o UseCase (retorna entidades de domínio)
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
     * 
     * GET /api/v1/planos/{plano}
     */
    public function get(Request $request, int $plano): JsonResponse
    {
        try {
            $planoDomain = $this->buscarPlanoUseCase->executar($plano);
            
            // Buscar modelo Eloquent para resposta (mantém compatibilidade com frontend)
            $planoModel = $this->planoRepository->buscarModeloPorId($plano);

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

