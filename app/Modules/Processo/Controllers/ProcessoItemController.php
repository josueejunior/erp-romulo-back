<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Services\ProcessoItemService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\ProcessoItem\Enums\UnidadeMedida;
use App\Application\ProcessoItem\UseCases\CriarProcessoItemUseCase;
use App\Application\ProcessoItem\UseCases\AtualizarProcessoItemUseCase;
use App\Application\ProcessoItem\UseCases\ExcluirProcessoItemUseCase;
use App\Application\ProcessoItem\UseCases\ListarProcessoItensUseCase;
use App\Application\ProcessoItem\UseCases\BuscarProcessoItemUseCase;
use App\Application\ProcessoItem\DTOs\CriarProcessoItemDTO;
use App\Application\ProcessoItem\DTOs\AtualizarProcessoItemDTO;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\ProcessoEmExecucaoException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use App\Http\Requests\ProcessoItem\ProcessoItemCreateRequest;
use App\Http\Requests\ProcessoItem\ProcessoItemUpdateRequest;
use Illuminate\Http\Request;

class ProcessoItemController extends BaseApiController
{

    protected ProcessoItemService $itemService;

    public function __construct(
        ProcessoItemService $itemService,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private CriarProcessoItemUseCase $criarProcessoItemUseCase,
        private AtualizarProcessoItemUseCase $atualizarProcessoItemUseCase,
        private ExcluirProcessoItemUseCase $excluirProcessoItemUseCase,
        private ListarProcessoItensUseCase $listarProcessoItensUseCase,
        private BuscarProcessoItemUseCase $buscarProcessoItemUseCase,
    ) {
        $this->itemService = $itemService; // Mantido para métodos específicos que ainda usam Service
        $this->service = $itemService; // Para HasDefaultActions
    }
    
    /**
     * API: Listar unidades de medida disponíveis
     */
    public function unidadesMedida()
    {
        return response()->json([
            'data' => UnidadeMedida::toArray()
        ]);
    }

    /**
     * API: Listar itens de um processo
     * 
     * ✅ DDD: Usa Use Case
     */
    public function list(Request $request)
    {
        try {
            $route = $request->route();
            $processoId = (int) $route->parameter('processo');
            
            if (!$processoId) {
                return response()->json(['message' => 'Processo não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (retorna array de entidades de domínio)
            $itens = $this->listarProcessoItensUseCase->executar($processoId, $empresa->id);
            
            // Buscar modelos Eloquent para serialização
            $models = collect($itens)->map(function ($itemDomain) {
                return $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);
            })->filter();
            
            return response()->json(['data' => $models]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar itens de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Buscar item específico
     * 
     * ✅ DDD: Usa Use Case
     */
    public function get(Request $request)
    {
        try {
            $route = $request->route();
            $itemId = (int) $route->parameter('item');
            $processoId = $route->hasParameter('processo') ? (int) $route->parameter('processo') : null;
            
            if (!$itemId) {
                return response()->json(['message' => 'Item não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (retorna entidade de domínio)
            $itemDomain = $this->buscarProcessoItemUseCase->executar($itemId, $processoId, $empresa->id);
            
            // Buscar modelo Eloquent para serialização
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);

            if (!$itemModel) {
                return response()->json(['message' => 'Erro ao buscar item'], 500);
            }

            return response()->json(['data' => $itemModel]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar item de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Criar item
     * 
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function store(ProcessoItemCreateRequest $request)
    {
        try {
            $route = $request->route();
            $processoId = (int) $route->parameter('processo');
            
            if (!$processoId) {
                return response()->json(['message' => 'Processo não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $dto = CriarProcessoItemDTO::fromArray($request->validated(), $processoId, $empresa->id);
            
            // Executar Use Case (retorna entidade de domínio)
            $itemDomain = $this->criarProcessoItemUseCase->executar($dto);
            
            // Buscar modelo Eloquent para serialização
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);

            if (!$itemModel) {
                return response()->json(['message' => 'Erro ao buscar item criado'], 500);
            }

            return response()->json(['data' => $itemModel], 201);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar item de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Atualizar item
     * 
     * ✅ DDD: Usa FormRequest, Use Case e DTO
     */
    public function update(ProcessoItemUpdateRequest $request, $id)
    {
        try {
            $route = $request->route();
            $processoId = (int) $route->parameter('processo');
            $itemId = (int) $id;
            
            if (!$processoId) {
                return response()->json(['message' => 'Processo não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $dto = AtualizarProcessoItemDTO::fromArray($request->validated(), $itemId, $processoId, $empresa->id);
            
            // Executar Use Case (retorna entidade de domínio)
            $itemDomain = $this->atualizarProcessoItemUseCase->executar($dto);
            
            // Buscar modelo Eloquent para serialização
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);

            if (!$itemModel) {
                return response()->json(['message' => 'Erro ao buscar item atualizado'], 500);
            }

            return response()->json(['data' => $itemModel]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar item de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $id,
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Excluir item
     * 
     * ✅ DDD: Usa Use Case
     */
    public function destroy(Request $request)
    {
        try {
            $route = $request->route();
            $processoId = (int) $route->parameter('processo');
            $itemId = (int) $route->parameter('item');
            
            if (!$processoId) {
                return response()->json(['message' => 'Processo não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case (valida propriedade e deleta)
            $this->excluirProcessoItemUseCase->executar($itemId, $processoId, $empresa->id);

            return response()->json(null, 204);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao excluir item de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId ?? null,
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Importar itens
     */
    public function importar(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $resultado = $this->itemService->importar($processo, $request->all());
            return response()->json($resultado, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function create(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarProcessoPodeEditar($processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        $proximoNumero = $this->itemService->calcularProximoNumeroItem($processo);

        return view('processo-itens.create', compact('processo', 'proximoNumero'));
    }

    /**
     * Web: Criar item (para views)
     */
    public function storeWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->storeItem($processo, $request->all());

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item adicionado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }

    public function edit(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarProcessoPodeEditar($processo);
            $this->itemService->validarItemPertenceProcesso($item, $processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        return view('processo-itens.edit', compact('processo', 'item'));
    }

    /**
     * Web: Atualizar item (para views)
     */
    public function updateWeb(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->updateItem($processo, $item, $request->all());

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item atualizado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Web: Excluir item (para views)
     */
    public function destroyWeb(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->delete($processo, $item);

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Item excluído com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * PATCH /processos/{processo}/itens/{item}/valor-final-disputa
     * Atualizar valor final pós-disputa (após lances)
     */
    public function atualizarValorFinalDisputa(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarItemPertenceProcesso($item, $processo);

            $request->validate([
                'valor_final_pos_disputa' => 'required|numeric|min:0',
            ]);

            $item->update([
                'valor_final_pos_disputa' => $request->valor_final_pos_disputa,
            ]);

            return response()->json([
                'message' => 'Valor final atualizado com sucesso',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PATCH /processos/{processo}/itens/{item}/valor-negociado
     * Atualizar valor negociado pós-julgamento
     */
    public function atualizarValorNegociado(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarItemPertenceProcesso($item, $processo);

            $request->validate([
                'valor_negociado_pos_julgamento' => 'required|numeric|min:0',
            ]);

            $item->update([
                'valor_negociado_pos_julgamento' => $request->valor_negociado_pos_julgamento,
            ]);

            return response()->json([
                'message' => 'Valor negociado atualizado com sucesso',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PATCH /processos/{processo}/itens/{item}/status
     * Atualizar status de habilitação do item
     */
    public function atualizarStatus(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarItemPertenceProcesso($item, $processo);

            $request->validate([
                'status_item' => 'required|string|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            ]);

            $item->update([
                'status_item' => $request->status_item,
            ]);

            return response()->json([
                'message' => 'Status atualizado com sucesso',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
