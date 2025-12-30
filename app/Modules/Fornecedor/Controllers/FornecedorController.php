<?php

namespace App\Modules\Fornecedor\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FornecedorResource;
use App\Application\Fornecedor\UseCases\ListarFornecedoresUseCase;
use App\Application\Fornecedor\UseCases\BuscarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\AtualizarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\DeletarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Application\Fornecedor\DTOs\AtualizarFornecedorDTO;
use App\Http\Requests\Fornecedor\FornecedorCreateRequest;
use App\Http\Requests\Fornecedor\FornecedorUpdateRequest;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use DomainException;

/**
 * Controller para gerenciamento de Fornecedores
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 */
class FornecedorController extends BaseApiController
{
    public function __construct(
        private ListarFornecedoresUseCase $listarFornecedoresUseCase,
        private BuscarFornecedorUseCase $buscarFornecedorUseCase,
        private CriarFornecedorUseCase $criarFornecedorUseCase,
        private AtualizarFornecedorUseCase $atualizarFornecedorUseCase,
        private DeletarFornecedorUseCase $deletarFornecedorUseCase,
    ) {}

    /**
     * Listar fornecedores
     * Retorna entidades de domínio transformadas via Resource
     */
    public function list(): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $filtros = request()->all();
            $filtros['empresa_id'] = $empresa->id;
            
            $paginado = $this->listarFornecedoresUseCase->executar($filtros);
            
            // Transformar entidades de domínio em JSON via Resource
            // O Resource aceita entidades de domínio e faz a conversão internamente
            $items = collect($paginado->items())->map(fn($fornecedor) => 
                new FornecedorResource($fornecedor)
            );
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar fornecedores');
        }
    }

    /**
     * Obter fornecedor específico
     * Retorna entidade de domínio transformada via Resource
     */
    public function get(int $id): JsonResponse
    {
        try {
            $fornecedorDomain = $this->buscarFornecedorUseCase->executar($id);
            
            return (new FornecedorResource($fornecedorDomain))
                ->response();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar fornecedor');
        }
    }

    /**
     * Criar fornecedor
     * Usa Form Request para validação e Resource para transformação
     */
    public function store(FornecedorCreateRequest $request): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Request já está validado via Form Request
            $validated = $request->validated();
            $validated['empresa_id'] = $empresa->id;
            
            $dto = CriarFornecedorDTO::fromArray($validated);
            $fornecedorDomain = $this->criarFornecedorUseCase->executar($dto);
            
            // Transformar via Resource (aceita entidade de domínio)
            return (new FornecedorResource($fornecedorDomain))
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar fornecedor');
        }
    }

    /**
     * Atualizar fornecedor
     * Usa Form Request para validação e Resource para transformação
     */
    public function update(FornecedorUpdateRequest $request, int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Request já está validado via Form Request
            $dto = AtualizarFornecedorDTO::fromRequest($request, $id);

            // Executar Use Case
            $fornecedorDomain = $this->atualizarFornecedorUseCase->executar($dto, $empresa->id);
            
            // Transformar via Resource (aceita entidade de domínio)
            return (new FornecedorResource($fornecedorDomain))
                ->response();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar fornecedor');
        }
    }

    /**
     * Deletar fornecedor
     */
    public function destroy(int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir fornecedores.'], 403);
        }

        try {
            $this->deletarFornecedorUseCase->executar($id);
            return response()->json(['message' => 'Fornecedor deletado com sucesso'], 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar fornecedor');
        }
    }
}
