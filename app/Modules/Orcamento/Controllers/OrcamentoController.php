<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Http\Resources\OrcamentoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Services\OrcamentoService;
use App\Application\Orcamento\UseCases\CriarOrcamentoUseCase;
use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Http\Requests\Orcamento\OrcamentoCreateRequest;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Http\Requests\Orcamento\OrcamentoItemUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller para gerenciamento de Orçamentos
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
class OrcamentoController extends BaseApiController
{
    use HasAuthContext;

    protected OrcamentoService $orcamentoService;

    public function __construct(
        OrcamentoService $orcamentoService, // Mantido para métodos específicos que ainda usam Service
        private CriarOrcamentoUseCase $criarOrcamentoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {
        $this->orcamentoService = $orcamentoService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar orçamentos de um item (Route::module)
     */
    public function list(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoDomain = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoDomain) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        return $this->index($request, $processoDomain, $itemModel);
    }

    /**
     * API: Buscar orçamento específico (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        $orcamentoId = $request->route()->parameter('orcamento');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->show($processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * Listar orçamentos de um item
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos orçamentos da empresa ativa.
     */
    public function index(Request $request, Processo $processo, ProcessoItem $item): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Validar que o item pertence ao processo
            if ($item->processo_id !== $processo->id) {
                return response()->json(['message' => 'Item não pertence ao processo'], 404);
            }

            $orcamentos = $this->orcamentoService->listByItem($item);

            return OrcamentoResource::collection($orcamentos);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar orçamentos');
        }
    }

    /**
     * API: Criar orçamento (Route::module)
     */
    public function store(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        return $this->storeWeb($request, $processoModel, $itemModel);
    }

    /**
     * Web: Criar orçamento
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function storeWeb(OrcamentoCreateRequest $request, Processo $processo, ProcessoItem $item): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Validar que o item pertence ao processo
            if ($item->processo_id !== $processo->id) {
                return response()->json(['message' => 'Item não pertence ao processo'], 404);
            }
            
            // Verificar permissão usando Policy
            $this->authorize('create', [$processo]);

            // Request já está validado via Form Request
            // Preparar dados para DTO
            $data = $request->validated();
            $data['processo_id'] = $processo->id;
            $data['processo_item_id'] = $item->id;
            $data['empresa_id'] = $empresa->id;
            
            // Usar Use Case DDD
            $dto = CriarOrcamentoDTO::fromArray($data);
            $orcamentoDomain = $this->criarOrcamentoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para Resource usando repository
            $orcamento = $this->orcamentoRepository->buscarModeloPorId(
                $orcamentoDomain->id,
                ['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']
            );
            
            if (!$orcamento) {
                return response()->json(['message' => 'Orçamento não encontrado após criação.'], 404);
            }
            
            return (new OrcamentoResource($orcamento))
                ->response()
                ->setStatusCode(201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar orçamento');
        }
    }

    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $orcamento = $this->orcamentoService->find($processo, $item, $orcamento, $empresa->id);
            return new OrcamentoResource($orcamento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar orçamento (Route::module)
     */
    public function update(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($id);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * API: Excluir orçamento (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$itemModel) {
            return response()->json(['message' => 'Item não encontrado.'], 404);
        }
        
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($id);
        if (!$orcamentoModel) {
            return response()->json(['message' => 'Orçamento não encontrado.'], 404);
        }
        
        return $this->destroyWeb($processoModel, $itemModel, $orcamentoModel);
    }

    /**
     * Web: Atualizar orçamento
     */
    public function updateWeb(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('update', $orcamento);

        try {
            $orcamento = $this->orcamentoService->update($processo, $item, $orcamento, $request->all(), $empresa->id);
            return new OrcamentoResource($orcamento);
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
     * Web: Excluir orçamento
     */
    public function destroyWeb(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        // Verificar permissão usando Policy
        $this->authorize('delete', $orcamento);

        try {
            $this->orcamentoService->delete($processo, $item, $orcamento);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cria um orçamento vinculado ao processo (pode ter múltiplos itens)
     */
    public function storeByProcesso(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        try {
            $orcamento = $this->orcamentoService->storeByProcesso($processo, $request->all(), $empresa->id);
            $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);
            return new OrcamentoResource($orcamento);
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
     * Lista orçamentos vinculados ao processo
     */
    public function indexByProcesso(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->orcamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $orcamentos = $this->orcamentoService->listByProcesso($processo);
            return OrcamentoResource::collection($orcamentos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Atualiza o fornecedor_escolhido de um orcamento_item específico
     * Usa Form Request para validação
     */
    public function updateOrcamentoItem(OrcamentoItemUpdateRequest $request, Processo $processo, Orcamento $orcamento, $orcamentoItemId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Request já está validado via Form Request
        $validated = $request->validated();

        try {
            $orcamento = $this->orcamentoService->updateOrcamentoItem(
                $processo, 
                $orcamento, 
                $orcamentoItemId, 
                $validated['fornecedor_escolhido'],
                $empresa->id
            );
            return new OrcamentoResource($orcamento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível alterar seleção de orçamentos em processos em execução.' ? 403 : 404);
        }
    }
}

