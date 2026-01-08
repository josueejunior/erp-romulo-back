<?php

namespace App\Modules\Custo\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Custo\Models\CustoIndireto;
use App\Modules\Custo\Services\CustoIndiretoService;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Application\CustoIndireto\UseCases\CriarCustoIndiretoUseCase;
use App\Application\CustoIndireto\UseCases\AtualizarCustoIndiretoUseCase;
use App\Application\CustoIndireto\UseCases\ExcluirCustoIndiretoUseCase;
use App\Application\CustoIndireto\UseCases\ListarCustoIndiretosUseCase;
use App\Application\CustoIndireto\UseCases\BuscarCustoIndiretoUseCase;
use App\Application\CustoIndireto\UseCases\ObterResumoCustoIndiretosUseCase;
use App\Application\CustoIndireto\DTOs\CriarCustoIndiretoDTO;
use App\Application\CustoIndireto\DTOs\AtualizarCustoIndiretoDTO;
use App\Application\CustoIndireto\DTOs\ListarCustoIndiretosDTO;
use App\Domain\Exceptions\NotFoundException;
use App\Http\Requests\CustoIndireto\CustoIndiretoCreateRequest;
use App\Http\Requests\CustoIndireto\CustoIndiretoUpdateRequest;
use App\Http\Controllers\Traits\HasAuthContext;
use Illuminate\Http\Request;

class CustoIndiretoController extends Controller
{
    use HasAuthContext;

    public function __construct(
        CustoIndiretoService $service,
        private CriarCustoIndiretoUseCase $criarCustoIndiretoUseCase,
        private AtualizarCustoIndiretoUseCase $atualizarCustoIndiretoUseCase,
        private ExcluirCustoIndiretoUseCase $excluirCustoIndiretoUseCase,
        private ListarCustoIndiretosUseCase $listarCustoIndiretosUseCase,
        private BuscarCustoIndiretoUseCase $buscarCustoIndiretoUseCase,
        private ObterResumoCustoIndiretosUseCase $obterResumoCustoIndiretosUseCase,
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {
        $this->service = $service; // Mantido para compatibilidade (método resumo ainda usa)
    }

    /**
     * Extrai o ID da rota
     */
    protected function getRouteId($route): ?int
    {
        $parameters = $route->parameters();
        // Tentar 'id' primeiro (conforme Route::module)
        $id = $parameters['id'] ?? null;
        return $id ? (int) $id : null;
    }

    /**
     * Sobrescrever handleList para usar service
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $params = $this->service->createListParamBag(array_merge($request->all(), $mergeParams));
            $custos = $this->service->list($params);
            return response()->json($custos);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar custos indiretos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleGet para usar service
     */
    protected function handleGet(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $id = $this->getRouteId($route);
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        try {
            $params = array_merge($request->all(), $mergeParams);
            $paramBag = $this->service->createFindByIdParamBag($params);
            $custo = $this->service->findById($id, $paramBag);
            
            if (!$custo) {
                return response()->json([
                    'message' => 'Custo indireto não encontrado.'
                ], 404);
            }
            
            return response()->json(['data' => $custo]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleStore para usar service
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $custo = $this->service->store($validator->validated());

            return response()->json([
                'message' => 'Custo indireto criado com sucesso',
                'data' => $custo
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para usar service
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $data = array_merge($request->all(), $mergeParams);
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $custo = $this->service->update($id, $validator->validated());

            return response()->json([
                'message' => 'Custo indireto atualizado com sucesso',
                'data' => $custo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleDestroy para usar service
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            $this->service->deleteById($id);

            return response()->json([
                'message' => 'Custo indireto removido com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Métodos de compatibilidade
     * 
     * ✅ DDD: Usa Use Case e DTO (delega para list())
     */
    public function index(Request $request)
    {
        return $this->list($request);
    }

    /**
     * Método list() para compatibilidade com Route::module()
     * 
     * ✅ DDD: Usa Use Case e DTO
     */
    public function list(Request $request)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Criar DTO a partir do Request
            $dto = ListarCustoIndiretosDTO::fromRequest($request->all(), $empresa->id);
            
            // Executar Use Case (retorna entidades de domínio paginadas)
            $paginado = $this->listarCustoIndiretosUseCase->executar($dto);
            
            // Buscar modelos Eloquent para serialização
            $models = collect($paginado->items())->map(function ($custoDomain) {
                return $this->custoIndiretoRepository->buscarModeloPorId($custoDomain->id);
            })->filter();
            
            return response()->json([
                'data' => $models,
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar custos indiretos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Método get() para compatibilidade com Route::module()
     * 
     * ✅ DDD: Usa Use Case
     */
    public function get(Request $request)
    {
        try {
            $route = $request->route();
            $id = $this->getRouteId($route);
            
            if (!$id) {
                return response()->json(['message' => 'ID não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (retorna entidade de domínio)
            $custoDomain = $this->buscarCustoIndiretoUseCase->executar($id, $empresa->id);
            
            // Buscar modelo Eloquent para serialização
            $custoModel = $this->custoIndiretoRepository->buscarModeloPorId($custoDomain->id);

            if (!$custoModel) {
                return response()->json(['message' => 'Erro ao buscar custo indireto'], 500);
            }

            return response()->json(['data' => $custoModel]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar custo indireto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function store(CustoIndiretoCreateRequest $request)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $dto = CriarCustoIndiretoDTO::fromArray($request->validated(), $empresa->id);
            
            // Executar Use Case (retorna entidade de domínio)
            $custoDomain = $this->criarCustoIndiretoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para serialização
            $custoModel = $this->custoIndiretoRepository->buscarModeloPorId($custoDomain->id);

            if (!$custoModel) {
                return response()->json(['message' => 'Erro ao buscar custo indireto criado'], 500);
            }

            return response()->json([
                'message' => 'Custo indireto criado com sucesso',
                'data' => $custoModel
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar custo indireto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ DDD: Usa Use Case (delega para get())
     */
    public function show($id)
    {
        $request = request();
        $request->route()->setParameter('id', $id);
        return $this->get($request);
    }

    /**
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function update(CustoIndiretoUpdateRequest $request, $id)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $custoIndiretoId = (int) $id;
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $dto = AtualizarCustoIndiretoDTO::fromArray($request->validated(), $custoIndiretoId, $empresa->id);
            
            // Executar Use Case (retorna entidade de domínio)
            $custoDomain = $this->atualizarCustoIndiretoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para serialização
            $custoModel = $this->custoIndiretoRepository->buscarModeloPorId($custoDomain->id);

            if (!$custoModel) {
                return response()->json(['message' => 'Erro ao buscar custo indireto atualizado'], 500);
            }

            return response()->json([
                'message' => 'Custo indireto atualizado com sucesso',
                'data' => $custoModel
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar custo indireto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ DDD: Usa Use Case
     */
    public function destroy($id)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $custoIndiretoId = (int) $id;
            
            // Executar Use Case (valida propriedade e deleta)
            $this->excluirCustoIndiretoUseCase->executar($custoIndiretoId, $empresa->id);

            return response()->json([
                'message' => 'Custo indireto removido com sucesso'
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao excluir custo indireto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna resumo de custos indiretos
     * 
     * ✅ DDD: Usa Use Case
     */
    public function resumo(Request $request)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros
            $filtros = array_merge($request->all(), [
                'empresa_id' => $empresa->id,
            ]);
            
            // Executar Use Case
            $resumo = $this->obterResumoCustoIndiretosUseCase->executar($filtros);
            
            return response()->json($resumo);
        } catch (\Exception $e) {
            \Log::error('Erro ao obter resumo de custos indiretos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}


