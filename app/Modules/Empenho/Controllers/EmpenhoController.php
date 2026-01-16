<?php

namespace App\Modules\Empenho\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
// ✅ DDD: Controller não importa modelos Eloquent diretamente
// Apenas usa interfaces de repositório e Use Cases
use App\Modules\Empenho\Services\EmpenhoService;
use App\Application\Empenho\UseCases\CriarEmpenhoUseCase;
use App\Application\Empenho\UseCases\ListarEmpenhosUseCase;
use App\Application\Empenho\UseCases\BuscarEmpenhoUseCase;
use App\Application\Empenho\UseCases\ConcluirEmpenhoUseCase;
use App\Application\Empenho\UseCases\AtualizarEmpenhoUseCase;
use App\Application\Empenho\UseCases\ExcluirEmpenhoUseCase;
use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Application\Empenho\DTOs\AtualizarEmpenhoDTO;
use App\Application\Empenho\DTOs\ListarEmpenhosDTO;
use App\Application\Empenho\Presenters\EmpenhoApiPresenter;
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
        private ConcluirEmpenhoUseCase $concluirEmpenhoUseCase,
        private AtualizarEmpenhoUseCase $atualizarEmpenhoUseCase,
        private ExcluirEmpenhoUseCase $excluirEmpenhoUseCase,
        private EmpenhoApiPresenter $presenter,
        private ProcessoRepositoryInterface $processoRepository,
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {
        $this->empenhoService = $empenhoService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar todos os empenhos da empresa
     * 
     * ✅ Arquitetura de referência:
     * - Controller: apenas orquestra (Request → DTO → Use Case → Presenter → Response)
     * - Use Case: recebe DTO com empresaId obrigatório
     * - Presenter: transforma modelos em arrays (serialização isolada)
     * - Zero lógica de negócio no Controller
     */
    public function listAll(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Criar DTO a partir do Request (encapsula validação e transformação)
            $dto = ListarEmpenhosDTO::fromRequest($request->all(), $empresa->id);
            
            // Executar Use Case (retorna entidades de domínio paginadas)
            $paginado = $this->listarEmpenhosUseCase->executar($dto);
            
            // Buscar modelos Eloquent para serialização (apenas para relacionamentos)
            $models = collect($paginado->items())->map(function ($empenhoDomain) {
                return $this->empenhoRepository->buscarModeloPorId(
                    $empenhoDomain->id,
                    ['processo', 'contrato', 'autorizacaoFornecimento']
                );
            })->filter();
            
            // Usar Presenter para serialização (responsabilidade isolada)
            $data = $this->presenter->presentCollection($models);
            
            return response()->json([
                'data' => $data,
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
     * API: Listar empenhos (Route::module)
     * 
     * ✅ DDD: Apenas delega para index
     */
    public function list(Request $request)
    {
        return $this->index($request);
    }

    /**
     * API: Buscar empenho (Route::module)
     * 
     * ✅ DDD: Apenas delega para show
     */
    public function get(Request $request)
    {
        return $this->show($request);
    }

    /**
     * Listar empenhos de um processo
     * 
     * ✅ Arquitetura de referência (mesmo padrão do listAll):
     * - Controller: apenas orquestra
     * - Use Case: recebe DTO com empresaId obrigatório
     * - Presenter: serialização isolada
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Criar DTO com processo_id da rota
            $requestData = array_merge($request->all(), ['processo_id' => $processoId]);
            $dto = ListarEmpenhosDTO::fromRequest($requestData, $empresa->id);
            
            // Executar Use Case
            $paginado = $this->listarEmpenhosUseCase->executar($dto);
            
            // Buscar modelos Eloquent para serialização
            $models = collect($paginado->items())->map(function ($empenhoDomain) {
                return $this->empenhoRepository->buscarModeloPorId(
                    $empenhoDomain->id,
                    ['processo', 'contrato', 'autorizacaoFornecimento']
                );
            })->filter();
            
            // Usar Presenter para serialização
            $data = $this->presenter->presentCollection($models);
            
            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar empenhos');
        }
    }

    /**
     * API: Criar empenho (Route::module)
     */
    /**
     * API: Criar empenho (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function store(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            
            // Validar dados (Form Request - validação de formato)
            $rules = (new EmpenhoCreateRequest())->rules();
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->storeWeb($validator->validated(), $processoId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao criar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao criar empenho');
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
    /**
     * Web: Criar empenho
     * 
     * ✅ DDD: Controller apenas orquestra, toda lógica no Use Case
     */
    public function storeWeb(array $data, int $processoId): JsonResponse
    {
        try {
            // Adicionar processo_id aos dados
            $data['processo_id'] = $processoId;
            
            // Usar Use Case DDD (contém toda a lógica de negócio, incluindo tenant)
            $dto = CriarEmpenhoDTO::fromArray($data);
            $empenhoDomain = $this->criarEmpenhoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para resposta usando repository
            $empenho = $this->empenhoRepository->buscarModeloPorId(
                $empenhoDomain->id,
                ['processo', 'contrato', 'autorizacaoFornecimento']
            );
            
            if (!$empenho) {
                return response()->json(['message' => 'Empenho não encontrado após criação.'], 404);
            }
            
            return response()->json([
                'message' => 'Empenho criado com sucesso',
                'data' => $empenho->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $statusCode = $e->getMessage() === 'Empenhos só podem ser criados para processos em execução.' ? 403 : 
                         ($e->getMessage() === 'Processo é obrigatório para criar empenho.' ? 400 : 400);
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar empenho');
        }
    }

    /**
     * Obter empenho específico
     * 
     * ✅ DDD: Controller não conhece Eloquent, apenas orquestra Use Cases
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empenhoId = (int) $request->route()->parameter('empenho');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (validações de negócio dentro do Use Case)
            $empenhoDomain = $this->buscarEmpenhoUseCase->executar($empenhoId);
            
            // Validar que o empenho pertence à empresa (regra de domínio - deveria estar no Use Case)
            // Por enquanto mantemos aqui, mas idealmente o Use Case deveria receber empresaId
            if ($empenhoDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent apenas para serialização (Infrastructure)
            $empenhoModel = $this->empenhoRepository->buscarModeloPorId(
                $empenhoDomain->id,
                ['processo', 'contrato', 'autorizacaoFornecimento', 'notasFiscais']
            );
            
            if (!$empenhoModel) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Carregar notas fiscais relacionadas com seus relacionamentos (fornecedor, processo, etc)
            $empenhoModel->load([
                'notasFiscais' => function ($query) {
                    $query->with(['fornecedor', 'processo', 'empenho']);
                }
            ]);
            
            return response()->json(['data' => $empenhoModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar empenho');
        }
    }

    /**
     * API: Atualizar empenho (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function update(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empenhoId = (int) $request->route()->parameter('empenho');
            
            // Validar dados (Form Request - validação de formato)
            $rules = (new EmpenhoCreateRequest())->rules();
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->updateWeb($request, $processoId, $empenhoId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $request->route()->parameter('empenho'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao atualizar empenho');
        }
    }

    /**
     * API: Excluir empenho (Route::module)
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function destroy(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empenhoId = (int) $request->route()->parameter('empenho');
            
            // Delegar para método Web (que usa Use Case - validação de regras de negócio)
            return $this->destroyWeb($processoId, $empenhoId);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $request->route()->parameter('empenho'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao excluir empenho');
        }
    }

    /**
     * Web: Atualizar empenho
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function updateWeb(Request $request, int $processoId, int $empenhoId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Validar dados usando as mesmas regras do create
            $rules = (new EmpenhoCreateRequest())->rules();
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            // Usar Use Case DDD (contém toda a lógica de negócio)
            $dto = AtualizarEmpenhoDTO::fromArray($data, $empenhoId);
            $empenhoDomain = $this->atualizarEmpenhoUseCase->executar($dto, $empresa->id);
            
            // Buscar modelo Eloquent para resposta usando repository
            $empenhoModel = $this->empenhoRepository->buscarModeloPorId(
                $empenhoDomain->id,
                ['processo', 'contrato', 'autorizacaoFornecimento']
            );
            
            if (!$empenhoModel) {
                return response()->json(['message' => 'Empenho não encontrado após atualização.'], 404);
            }
            
            return response()->json([
                'message' => 'Empenho atualizado com sucesso',
                'data' => $empenhoModel->toArray(),
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar empenho');
        }
    }

    /**
     * Web: Excluir empenho
     * 
     * ✅ DDD: Usa Use Case, não Service
     */
    public function destroyWeb(int $processoId, int $empenhoId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Usar Use Case DDD (contém toda a lógica de negócio)
            $this->excluirEmpenhoUseCase->executar($empenhoId, $empresa->id);
            return response()->json(null, 204);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir empenho');
        }
    }

    /**
     * API: Concluir empenho
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function concluir(Request $request): JsonResponse
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $empenhoId = (int) $request->route()->parameter('empenho');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (validações de negócio dentro do Use Case)
            $empenhoConcluido = $this->concluirEmpenhoUseCase->executar($empenhoId, $empresa->id, $processoId);
            
            // Buscar modelo Eloquent apenas para serialização (Infrastructure)
            $empenhoModel = $this->empenhoRepository->buscarModeloPorId(
                $empenhoConcluido->id,
                ['processo', 'contrato', 'autorizacaoFornecimento']
            );
            
            return response()->json([
                'message' => 'Empenho concluído com sucesso',
                'data' => $empenhoModel ? $empenhoModel->toArray() : null
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao concluir empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $request->route()->parameter('empenho'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->handleException($e, 'Erro ao concluir empenho');
        }
    }
}

