<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Http\Resources\OrcamentoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Modules\Orcamento\Services\OrcamentoService;
use App\Application\Orcamento\UseCases\CriarOrcamentoUseCase;
use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrcamentoController extends BaseApiController
{
    use HasDefaultActions;

    protected OrcamentoService $orcamentoService;

    public function __construct(
        OrcamentoService $orcamentoService,
        private CriarOrcamentoUseCase $criarOrcamentoUseCase,
    ) {
        $this->orcamentoService = $orcamentoService;
        $this->service = $orcamentoService; // Para HasDefaultActions
    }

    /**
     * API: Listar orçamentos de um item (Route::module)
     */
    public function list(Request $request)
    {
        return $this->index($request, 
            Processo::findOrFail($request->route()->parameter('processo')),
            ProcessoItem::findOrFail($request->route()->parameter('item'))
        );
    }

    /**
     * API: Buscar orçamento específico (Route::module)
     */
    public function get(Request $request)
    {
        return $this->show(
            Processo::findOrFail($request->route()->parameter('processo')),
            ProcessoItem::findOrFail($request->route()->parameter('item')),
            Orcamento::findOrFail($request->route()->parameter('orcamento'))
        );
    }

    public function index(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->orcamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->orcamentoService->validarItemPertenceProcesso($item, $processo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }

        $orcamentos = $this->orcamentoService->listByItem($item);

        return OrcamentoResource::collection($orcamentos);
    }

    /**
     * API: Criar orçamento (Route::module)
     */
    public function store(Request $request)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $item = ProcessoItem::findOrFail($route->parameter('item'));
        
        return $this->storeWeb($request, $processo, $item);
    }

    /**
     * Web: Criar orçamento
     */
    public function storeWeb(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        try {
            // Preparar dados para DTO
            $data = $request->all();
            $data['processo_id'] = $processo->id;
            $data['processo_item_id'] = $item->id;
            $data['empresa_id'] = $empresa->id;
            
            // Usar Use Case DDD
            $dto = CriarOrcamentoDTO::fromArray($data);
            $orcamentoDomain = $this->criarOrcamentoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para Resource
            $orcamento = Orcamento::findOrFail($orcamentoDomain->id);
            $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);
            
            return new OrcamentoResource($orcamento);
        } catch (ValidationException $e) {
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
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $item = ProcessoItem::findOrFail($route->parameter('item'));
        $orcamento = Orcamento::findOrFail($id);
        
        return $this->updateWeb($request, $processo, $item, $orcamento);
    }

    /**
     * API: Excluir orçamento (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $item = ProcessoItem::findOrFail($route->parameter('item'));
        $orcamento = Orcamento::findOrFail($id);
        
        return $this->destroyWeb($processo, $item, $orcamento);
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
     */
    public function updateOrcamentoItem(Request $request, Processo $processo, Orcamento $orcamento, $orcamentoItemId)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $validated = $request->validate([
            'fornecedor_escolhido' => 'required|boolean',
        ]);

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

