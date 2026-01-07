<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Plano\UseCases\ListarPlanosUseCase;
use App\Application\Plano\UseCases\BuscarPlanoUseCase;
use App\Application\Plano\UseCases\CriarPlanoUseCase;
use App\Application\Plano\UseCases\AtualizarPlanoUseCase;
use App\Application\Plano\UseCases\DeletarPlanoUseCase;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Http\Requests\Plano\CriarPlanoRequest;
use App\Http\Requests\Plano\AtualizarPlanoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller Admin para gerenciamento de Planos
 * 
 * Refatorado para usar DDD (Domain-Driven Design)
 */
class AdminPlanoController extends Controller
{
    public function __construct(
        private ListarPlanosUseCase $listarPlanosUseCase,
        private BuscarPlanoUseCase $buscarPlanoUseCase,
        private CriarPlanoUseCase $criarPlanoUseCase,
        private AtualizarPlanoUseCase $atualizarPlanoUseCase,
        private DeletarPlanoUseCase $deletarPlanoUseCase,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Lista todos os planos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [];
            
            if ($request->has('ativo')) {
                $filtros['ativo'] = $request->boolean('ativo');
            }

            // Buscar planos usando o UseCase
            $planosDomain = $this->listarPlanosUseCase->executar($filtros);

            // Converter para modelos Eloquent para resposta
            $planos = $planosDomain->map(function ($planoDomain) {
                return $this->planoRepository->buscarModeloPorId($planoDomain->id);
            })->filter();

            return response()->json([
                'data' => $planos->values()->all(),
                'meta' => [
                    'total' => $planos->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar planos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca um plano específico
     */
    public function show(int $plano): JsonResponse
    {
        try {
            $planoDomain = $this->buscarPlanoUseCase->executar($plano);
            $planoModel = $this->planoRepository->buscarModeloPorId($plano);

            return response()->json([
                'data' => $planoModel
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar plano: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um novo plano
     */
    public function store(CriarPlanoRequest $request): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Executar Use Case
            $planoDomain = $this->criarPlanoUseCase->executar($validated);

            // Buscar modelo para resposta
            $planoModel = $this->planoRepository->buscarModeloPorId($planoDomain->id);

            return response()->json([
                'message' => 'Plano criado com sucesso',
                'data' => $planoModel,
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar plano: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza um plano existente
     */
    public function update(AtualizarPlanoRequest $request, int $plano): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Executar Use Case
            $planoDomain = $this->atualizarPlanoUseCase->executar($plano, $validated);

            // Buscar modelo para resposta
            $planoModel = $this->planoRepository->buscarModeloPorId($planoDomain->id);

            return response()->json([
                'message' => 'Plano atualizado com sucesso',
                'data' => $planoModel,
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar plano: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deleta um plano
     */
    public function destroy(int $plano): JsonResponse
    {
        try {
            // Executar Use Case
            $this->deletarPlanoUseCase->executar($plano);

            return response()->json([
                'message' => 'Plano deletado com sucesso',
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar plano: ' . $e->getMessage(),
            ], 500);
        }
    }
}



