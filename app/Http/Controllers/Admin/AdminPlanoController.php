<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Plano\UseCases\ListarPlanosAdminUseCase;
use App\Application\Plano\UseCases\BuscarPlanoUseCase;
use App\Application\Plano\UseCases\CriarPlanoUseCase;
use App\Application\Plano\UseCases\AtualizarPlanoUseCase;
use App\Application\Plano\UseCases\DeletarPlanoUseCase;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Http\Requests\Plano\CriarPlanoRequest;
use App\Http\Requests\Plano\AtualizarPlanoRequest;
use App\Http\Responses\ApiResponse;
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
        private ListarPlanosAdminUseCase $listarPlanosAdminUseCase,
        private BuscarPlanoUseCase $buscarPlanoUseCase,
        private CriarPlanoUseCase $criarPlanoUseCase,
        private AtualizarPlanoUseCase $atualizarPlanoUseCase,
        private DeletarPlanoUseCase $deletarPlanoUseCase,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Lista todos os planos
     * 🔥 DDD: Controller fino - delega para UseCase que evita N+1
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [];
            
            if ($request->has('ativo')) {
                $filtros['ativo'] = $request->boolean('ativo');
            }

            // UseCase já retorna dados formatados (evita N+1)
            $planos = $this->listarPlanosAdminUseCase->executar($filtros);

            return ApiResponse::collection($planos->all());
        } catch (\Exception $e) {
            return ApiResponse::error('Erro ao listar planos.', 500);
        }
    }

    /**
     * Busca um plano específico
     * 🔥 DDD: Controller fino - padronizado response
     */
    public function show(int $plano): JsonResponse
    {
        try {
            $planoDomain = $this->buscarPlanoUseCase->executar($plano);
            $planoModel = $this->planoRepository->buscarModeloPorId($plano);

            if (!$planoModel) {
                return ApiResponse::error('Plano não encontrado.', 404);
            }

            return ApiResponse::item($planoModel->toArray());
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Erro ao buscar plano.', 500);
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

            return ApiResponse::success(
                'Plano criado com sucesso',
                $planoModel?->toArray(),
                201
            );
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Erro ao criar plano.', 500);
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

            return ApiResponse::success(
                'Plano atualizado com sucesso',
                $planoModel?->toArray()
            );
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Erro ao atualizar plano.', 500);
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

            return ApiResponse::success('Plano deletado com sucesso');
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Erro ao deletar plano.', 500);
        }
    }
}



