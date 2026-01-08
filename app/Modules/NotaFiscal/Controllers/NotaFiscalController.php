<?php

namespace App\Modules\NotaFiscal\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Modules\NotaFiscal\Services\NotaFiscalService;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\UseCases\ListarNotasFiscaisUseCase;
use App\Application\NotaFiscal\UseCases\BuscarNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Http\Requests\NotaFiscal\NotaFiscalCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciamento de Notas Fiscais
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
class NotaFiscalController extends BaseApiController
{
    use HasAuthContext;

    protected NotaFiscalService $notaFiscalService;

    public function __construct(
        NotaFiscalService $notaFiscalService, // Mantido para métodos específicos que ainda usam Service
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
        private ListarNotasFiscaisUseCase $listarNotasFiscaisUseCase,
        private BuscarNotaFiscalUseCase $buscarNotaFiscalUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {
        $this->notaFiscalService = $notaFiscalService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar todas as notas fiscais da empresa (sem filtro de processo)
     */
    public function listAll(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros
            $filtros = [
                'empresa_id' => $empresa->id,
            ];
            
            // Adicionar filtros opcionais da query string
            if ($request->has('processo_id') && $request->processo_id) {
                $filtros['processo_id'] = $request->processo_id;
            }
            
            if ($request->has('empenho_id') && $request->empenho_id) {
                $filtros['empenho_id'] = $request->empenho_id;
            }
            
            if ($request->has('fornecedor_id') && $request->fornecedor_id) {
                $filtros['fornecedor_id'] = $request->fornecedor_id;
            }
            
            if ($request->has('situacao') && $request->situacao) {
                $filtros['situacao'] = $request->situacao;
            }
            
            // Adicionar per_page aos filtros se fornecido
            if ($request->has('per_page')) {
                $filtros['per_page'] = $request->per_page;
            }
            
            // Buscar modelos diretamente (mais eficiente e mantém relacionamentos)
            $query = NotaFiscal::query();
            
            // Aplicar filtros
            if (isset($filtros['empresa_id'])) {
                $query->where('empresa_id', $filtros['empresa_id']);
            }
            if (isset($filtros['processo_id'])) {
                $query->where('processo_id', $filtros['processo_id']);
            }
            if (isset($filtros['empenho_id'])) {
                $query->where('empenho_id', $filtros['empenho_id']);
            }
            if (isset($filtros['fornecedor_id'])) {
                $query->where('fornecedor_id', $filtros['fornecedor_id']);
            }
            if (isset($filtros['situacao'])) {
                $query->where('situacao', $filtros['situacao']);
            }
            
            // Carregar relacionamentos
            $query->with(['processo', 'empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);
            
            // Paginar
            $perPage = $filtros['per_page'] ?? 15;
            $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);
            
            // Transformar para array
            $items = $paginator->getCollection()->map(function ($notaFiscal) {
                $array = $notaFiscal->toArray();
                // Garantir que processo_id está presente
                if (!isset($array['processo_id']) && $notaFiscal->processo) {
                    $array['processo_id'] = $notaFiscal->processo->id;
                }
                return $array;
            });
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar notas fiscais');
        }
    }

    /**
     * API: Listar notas fiscais (Route::module)
     */
    public function list(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        return $this->index($processoModel);
    }

    /**
     * API: Buscar nota fiscal (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $notaFiscalId = $request->route()->parameter('notaFiscal');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId($notaFiscalId);
        if (!$notaFiscalModel) {
            return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
        }
        
        return $this->show($processoModel, $notaFiscalModel);
    }

    /**
     * Listar notas fiscais de um processo
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados das notas fiscais da empresa ativa.
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
            $paginado = $this->listarNotasFiscaisUseCase->executar($filtros);
            
            // Transformar para resposta
            $items = collect($paginado->items())->map(function ($notaFiscalDomain) {
                // Buscar modelo Eloquent para incluir relacionamentos
                $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
                );
                return $notaFiscalModel ? $notaFiscalModel->toArray() : null;
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
            return $this->handleException($e, 'Erro ao listar notas fiscais');
        }
    }

    /**
     * API: Criar nota fiscal (Route::module)
     */
    public function store(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        // Validar dados manualmente (mesmo padrão do EmpenhoController)
        $rules = (new NotaFiscalCreateRequest())->rules();
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Passar dados validados diretamente como array
        return $this->storeWeb($validator->validated(), $processoModel);
    }

    /**
     * Web: Criar nota fiscal
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request ou array validado)
     * - Chama um Application Service
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id
     * - Não acessa Tenant
     * - Não sabe se existe multi-tenant
     * - Não filtra nada por tenant_id
     */
    public function storeWeb(NotaFiscalCreateRequest|array $request, Processo $processo): JsonResponse
    {
        try {
            // Se for array, já está validado. Se for FormRequest, chamar validated()
            $data = is_array($request) ? $request : $request->validated();
            $data['processo_id'] = $processo->id;
            
            // Usar Use Case DDD (contém toda a lógica de negócio, incluindo tenant)
            $dto = CriarNotaFiscalDTO::fromArray($data);
            $notaFiscalDomain = $this->criarNotaFiscalUseCase->executar($dto);
            
            // Buscar modelo Eloquent para resposta usando repository
            $notaFiscal = $this->notaFiscalRepository->buscarModeloPorId(
                $notaFiscalDomain->id,
                ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
            );
            
            if (!$notaFiscal) {
                return response()->json(['message' => 'Nota fiscal não encontrada após criação.'], 404);
            }
            
            return response()->json([
                'message' => 'Nota fiscal criada com sucesso',
                'data' => $notaFiscal->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $statusCode = $e->getMessage() === 'Notas fiscais só podem ser criadas para processos em execução.' ? 403 : 
                         ($e->getMessage() === 'Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.' ? 400 : 400);
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar nota fiscal');
        }
    }

    /**
     * Obter nota fiscal específica
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados da nota fiscal da empresa ativa.
     */
    public function show(Processo $processo, NotaFiscal $notaFiscal): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo e nota fiscal pertencem à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Executar Use Case
            $notaFiscalDomain = $this->buscarNotaFiscalUseCase->executar($notaFiscal->id);
            
            // Validar que a nota fiscal pertence à empresa ativa
            if ($notaFiscalDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                $notaFiscalDomain->id,
                ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
            );
            
            if (!$notaFiscalModel) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            return response()->json(['data' => $notaFiscalModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar nota fiscal');
        }
    }

    /**
     * API: Atualizar nota fiscal (Route::module)
     */
    public function update(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            Log::debug('NotaFiscalController::update - Iniciando', [
                'processo_id' => $processoId,
                'nota_fiscal_id' => $notaFiscalId,
                'empresa_id' => $empresa->id,
            ]);
            
            // Buscar modelos Eloquent diretamente (mais confiável)
            $processo = $this->processoRepository->buscarModeloPorId($processoId);
            if (!$processo) {
                Log::warning('NotaFiscalController::update - Processo não encontrado', [
                    'processo_id' => $processoId,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                Log::warning('NotaFiscalController::update - Processo não pertence à empresa', [
                    'processo_id' => $processoId,
                    'processo_empresa_id' => $processo->empresa_id,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar nota fiscal diretamente pelo modelo
            $notaFiscal = $this->notaFiscalRepository->buscarModeloPorId($notaFiscalId);
            if (!$notaFiscal) {
                Log::warning('NotaFiscalController::update - Nota fiscal não encontrada', [
                    'nota_fiscal_id' => $notaFiscalId,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            Log::debug('NotaFiscalController::update - Nota fiscal encontrada', [
                'nota_fiscal_id' => $notaFiscal->id,
                'nota_fiscal_processo_id' => $notaFiscal->processo_id,
                'nota_fiscal_empresa_id' => $notaFiscal->empresa_id,
                'processo_id' => $processoId,
                'empresa_id' => $empresa->id,
            ]);
            
            // Validar que a nota fiscal pertence ao processo e à empresa
            if ($notaFiscal->processo_id !== $processoId) {
                Log::warning('NotaFiscalController::update - Nota fiscal não pertence ao processo', [
                    'nota_fiscal_id' => $notaFiscal->id,
                    'nota_fiscal_processo_id' => $notaFiscal->processo_id,
                    'processo_id' => $processoId,
                ]);
                return response()->json(['message' => 'Nota fiscal não pertence ao processo'], 404);
            }
            
            if ($notaFiscal->empresa_id !== $empresa->id) {
                Log::warning('NotaFiscalController::update - Nota fiscal não pertence à empresa', [
                    'nota_fiscal_id' => $notaFiscal->id,
                    'nota_fiscal_empresa_id' => $notaFiscal->empresa_id,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            // Validar dados manualmente (mesmo padrão do store)
            $rules = (new NotaFiscalCreateRequest())->rules();
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return $this->updateWeb($request, $processo, $notaFiscal);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/nota fiscal para atualizar', [
                'processo_id' => $request->route()->parameter('processo'),
                'nota_fiscal_id' => $request->route()->parameter('notaFiscal'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou nota fiscal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Excluir nota fiscal (Route::module)
     */
    public function destroy(Request $request)
    {
        try {
            $processoId = (int) $request->route()->parameter('processo');
            $notaFiscalId = (int) $request->route()->parameter('notaFiscal');
            $empresa = $this->getEmpresaAtivaOrFail();
            
            Log::debug('NotaFiscalController::destroy - Iniciando', [
                'processo_id' => $processoId,
                'nota_fiscal_id' => $notaFiscalId,
                'empresa_id' => $empresa->id,
            ]);
            
            // Buscar modelos Eloquent diretamente (mais confiável)
            $processo = $this->processoRepository->buscarModeloPorId($processoId);
            if (!$processo) {
                Log::warning('NotaFiscalController::destroy - Processo não encontrado', [
                    'processo_id' => $processoId,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                Log::warning('NotaFiscalController::destroy - Processo não pertence à empresa', [
                    'processo_id' => $processoId,
                    'processo_empresa_id' => $processo->empresa_id,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar nota fiscal diretamente pelo modelo
            $notaFiscal = $this->notaFiscalRepository->buscarModeloPorId($notaFiscalId);
            if (!$notaFiscal) {
                Log::warning('NotaFiscalController::destroy - Nota fiscal não encontrada', [
                    'nota_fiscal_id' => $notaFiscalId,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            Log::debug('NotaFiscalController::destroy - Nota fiscal encontrada', [
                'nota_fiscal_id' => $notaFiscal->id,
                'nota_fiscal_processo_id' => $notaFiscal->processo_id,
                'nota_fiscal_empresa_id' => $notaFiscal->empresa_id,
                'processo_id' => $processoId,
                'empresa_id' => $empresa->id,
            ]);
            
            // Validar que a nota fiscal pertence ao processo e à empresa
            if ($notaFiscal->processo_id !== $processoId) {
                Log::warning('NotaFiscalController::destroy - Nota fiscal não pertence ao processo', [
                    'nota_fiscal_id' => $notaFiscal->id,
                    'nota_fiscal_processo_id' => $notaFiscal->processo_id,
                    'processo_id' => $processoId,
                ]);
                return response()->json(['message' => 'Nota fiscal não pertence ao processo'], 404);
            }
            
            if ($notaFiscal->empresa_id !== $empresa->id) {
                Log::warning('NotaFiscalController::destroy - Nota fiscal não pertence à empresa', [
                    'nota_fiscal_id' => $notaFiscal->id,
                    'nota_fiscal_empresa_id' => $notaFiscal->empresa_id,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            return $this->destroyWeb($processo, $notaFiscal);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/nota fiscal para deletar', [
                'processo_id' => $request->route()->parameter('processo'),
                'nota_fiscal_id' => $request->route()->parameter('notaFiscal'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou nota fiscal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Web: Atualizar nota fiscal
     */
    public function updateWeb(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Validar dados usando as mesmas regras do create
            $rules = (new NotaFiscalCreateRequest())->rules();
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            $notaFiscal = $this->notaFiscalService->update($processo, $notaFiscal, $data, $request, $empresa->id);
            return response()->json($notaFiscal);
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
     * Web: Excluir nota fiscal
     */
    public function destroyWeb(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->notaFiscalService->delete($processo, $notaFiscal, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}

