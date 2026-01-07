<?php

namespace App\Modules\Empenho\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\Empenho\Services\EmpenhoService;
use App\Application\Empenho\UseCases\CriarEmpenhoUseCase;
use App\Application\Empenho\UseCases\ListarEmpenhosUseCase;
use App\Application\Empenho\UseCases\BuscarEmpenhoUseCase;
use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Http\Requests\Empenho\EmpenhoCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciamento de Empenhos
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Não acessa modelos Eloquent diretamente (exceto para relacionamentos)
 * 
 * Segue o mesmo padrão do AssinaturaController e FornecedorController:
 * - Tenant ID: Obtido automaticamente via tenancy()->tenant (middleware já inicializou)
 * - Empresa ID: Obtido automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID
 */
class EmpenhoController extends BaseApiController
{
    use HasAuthContext;

    protected EmpenhoService $empenhoService;

    public function __construct(
        EmpenhoService $empenhoService, // Mantido para métodos específicos que ainda usam Service
        private CriarEmpenhoUseCase $criarEmpenhoUseCase,
        private ListarEmpenhosUseCase $listarEmpenhosUseCase,
        private BuscarEmpenhoUseCase $buscarEmpenhoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {
        $this->empenhoService = $empenhoService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar empenhos (Route::module)
     */
    public function list(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent via repository (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            if (!$processo) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            return $this->index($processo);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo para listar empenhos', [
                'processo_id' => $request->route()->parameter('processo'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Buscar empenho (Route::module)
     */
    public function get(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $empenhoId = $request->route()->parameter('empenho');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($empenhoId);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->show($processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $request->route()->parameter('empenho'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar empenho: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Listar empenhos de um processo
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos empenhos da empresa ativa.
     */
    public function index(Processo $processo): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Preparar filtros
            $filtros = [
                'empresa_id' => $empresa->id,
                'processo_id' => $processo->id,
            ];
            
            // Executar Use Case
            $paginado = $this->listarEmpenhosUseCase->executar($filtros);
            
            // Transformar para resposta
            $items = collect($paginado->items())->map(function ($empenhoDomain) {
                // Buscar modelo Eloquent para incluir relacionamentos
                $empenhoModel = $this->empenhoRepository->buscarModeloPorId(
                    $empenhoDomain->id,
                    ['processo', 'contrato', 'autorizacaoFornecimento']
                );
                return $empenhoModel ? $empenhoModel->toArray() : null;
            })->filter();
            
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
            return $this->handleException($e, 'Erro ao listar empenhos');
        }
    }

    /**
     * API: Criar empenho (Route::module)
     */
    public function store(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent via repository (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            if (!$processo) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            return $this->storeWeb($request, $processo);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo para criar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Web: Criar empenho
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Chama um Application Service
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id
     * - Não acessa Tenant
     * - Não sabe se existe multi-tenant
     * - Não filtra nada por tenant_id
     */
    public function storeWeb(Request $request, Processo $processo): JsonResponse
    {
        try {
            // Validar dados usando Form Request
            $empenhoRequest = EmpenhoCreateRequest::createFrom($request);
            $empenhoRequest->setContainer(app());
            $empenhoRequest->validateResolved();
            
            // Preparar dados para DTO
            $data = $empenhoRequest->validated();
            $data['processo_id'] = $processo->id;
            
            // Usar Use Case DDD (contém toda a lógica de negócio, incluindo tenant)
            $dto = CriarEmpenhoDTO::fromArray($data);
            $empenhoDomain = $this->criarEmpenhoUseCase->executar($dto);
            
            // Buscar modelo Eloquent via repository (DDD)
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id, [
                'processo', 
                'contrato', 
                'autorizacaoFornecimento'
            ]);
            
            if (!$empenho) {
                return response()->json(['message' => 'Empenho não encontrado após criação.'], 404);
            }
            
            return response()->json([
                'message' => 'Empenho criado com sucesso',
                'data' => $empenho->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Empenhos só podem ser criados para processos em execução.' ? 403 : 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar empenho');
        }
    }

    /**
     * Obter empenho específico
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados do empenho da empresa ativa.
     */
    public function show(Processo $processo, Empenho $empenho): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo e empenho pertencem à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Executar Use Case
            $empenhoDomain = $this->buscarEmpenhoUseCase->executar($empenho->id);
            
            // Validar que o empenho pertence à empresa ativa
            if ($empenhoDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $empenhoModel = $this->empenhoRepository->buscarModeloPorId(
                $empenhoDomain->id,
                ['processo', 'contrato', 'autorizacaoFornecimento']
            );
            
            if (!$empenhoModel) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            return response()->json(['data' => $empenhoModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar empenho');
        }
    }

    /**
     * API: Atualizar empenho (Route::module)
     */
    public function update(Request $request, $id)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($id);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->updateWeb($request, $processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/empenho para atualizar', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou empenho: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Excluir empenho (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($id);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->destroyWeb($processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/empenho para deletar', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou empenho: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Web: Atualizar empenho
     */
    public function updateWeb(Request $request, Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $empenho = $this->empenhoService->update($processo, $empenho, $request->all(), $empresa->id);
            return response()->json($empenho);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Web: Excluir empenho
     */
    public function destroyWeb(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->empenhoService->delete($processo, $empenho, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}

