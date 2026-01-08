<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FormacaoPrecoResource;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\FormacaoPreco;
use App\Modules\Orcamento\Services\FormacaoPrecoService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface;
use App\Http\Controllers\Traits\ResolvesContext;
use App\Domain\Exceptions\FormacaoPrecoNaoEncontradaException;
use App\Domain\Exceptions\ProcessoEmExecucaoException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use App\Domain\Exceptions\NotFoundException;
use App\Http\Requests\FormacaoPreco\FormacaoPrecoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciamento de formação de preços
 * 
 * ✅ DDD Enterprise-Grade:
 * - Usa trait para resolver contexto (elimina repetição)
 * - Service valida vínculos e regras de negócio
 * - Domain Exceptions específicas
 * - Controller apenas orquestra
 */
class FormacaoPrecoController extends BaseApiController
{
    use ResolvesContext;

    public function __construct(
        private FormacaoPrecoService $formacaoPrecoService,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private FormacaoPrecoRepositoryInterface $formacaoPrecoRepository,
    ) {
        $this->service = $formacaoPrecoService; // Para HasDefaultActions
    }

    /**
     * API: Buscar formação de preço (Route::module)
     * 
     * ✅ DDD: Usa resolveContext para eliminar repetição
     */
    public function get(Request $request): JsonResponse|FormacaoPrecoResource
    {
        try {
            [$processo, $item, $orcamento] = $this->resolveContext($request);
            $empresaId = $this->getEmpresaAtivaOrFail()->id;

            $formacaoPreco = $this->formacaoPrecoService->find($processo, $item, $orcamento, $empresaId);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (FormacaoPrecoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao buscar formação de preço'], 500);
        }
    }

    /**
     * Web: Buscar formação de preço
     */
    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento): JsonResponse|FormacaoPrecoResource
    {
        try {
            $empresaId = $this->getEmpresaAtivaOrFail()->id;
            $formacaoPreco = $this->formacaoPrecoService->find($processo, $item, $orcamento, $empresaId);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (FormacaoPrecoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao buscar formação de preço'], 500);
        }
    }

    /**
     * API: Criar formação de preço (Route::module)
     * 
     * ✅ DDD: 
     * - Usa resolveContext para eliminar repetição
     * - FormRequest valida dados (Service assume válidos)
     */
    public function store(FormacaoPrecoRequest $request): JsonResponse|FormacaoPrecoResource
    {
        try {
            [$processo, $item, $orcamento] = $this->resolveContext($request);
            $empresaId = $this->getEmpresaAtivaOrFail()->id;

            // FormRequest já validou os dados
            $formacaoPreco = $this->formacaoPrecoService->store(
                $processo,
                $item,
                $orcamento,
                $request->validated(),
                $empresaId
            );

            return new FormacaoPrecoResource($formacaoPreco);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao criar formação de preço'], 500);
        }
    }

    /**
     * API: Atualizar formação de preço (Route::module)
     * 
     * ✅ DDD: 
     * - Usa resolveContext para eliminar repetição
     * - FormRequest valida dados (Service assume válidos)
     */
    public function update(FormacaoPrecoRequest $request, int $id): JsonResponse|FormacaoPrecoResource
    {
        try {
            [$processo, $item, $orcamento] = $this->resolveContext($request);
            $empresaId = $this->getEmpresaAtivaOrFail()->id;

            $formacaoPreco = $this->formacaoPrecoRepository->buscarModeloPorId($id);
            if (!$formacaoPreco) {
                throw new FormacaoPrecoNaoEncontradaException();
            }

            // FormRequest já validou os dados
            $formacaoPreco = $this->formacaoPrecoService->update(
                $processo,
                $item,
                $orcamento,
                $formacaoPreco,
                $request->validated(),
                $empresaId
            );

            return new FormacaoPrecoResource($formacaoPreco);
        } catch (FormacaoPrecoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao atualizar formação de preço'], 500);
        }
    }

    /**
     * Web: Criar formação de preço
     * 
     * ✅ DDD: 
     * - Service valida tudo, controller apenas orquestra
     * - FormRequest valida dados
     */
    public function storeWeb(FormacaoPrecoRequest $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento): JsonResponse|FormacaoPrecoResource
    {
        try {
            $empresaId = $this->getEmpresaAtivaOrFail()->id;
            // FormRequest já validou os dados
            $formacaoPreco = $this->formacaoPrecoService->store($processo, $item, $orcamento, $request->validated(), $empresaId);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao criar formação de preço'], 500);
        }
    }

    /**
     * Web: Atualizar formação de preço
     * 
     * ✅ DDD: 
     * - Service valida tudo, controller apenas orquestra
     * - FormRequest valida dados
     */
    public function updateWeb(FormacaoPrecoRequest $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento, FormacaoPreco $formacaoPreco): JsonResponse|FormacaoPrecoResource
    {
        try {
            $empresaId = $this->getEmpresaAtivaOrFail()->id;
            // FormRequest já validou os dados
            $formacaoPreco = $this->formacaoPrecoService->update($processo, $item, $orcamento, $formacaoPreco, $request->validated(), $empresaId);
            return new FormacaoPrecoResource($formacaoPreco);
        } catch (FormacaoPrecoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar formação de preço: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erro ao atualizar formação de preço'], 500);
        }
    }
}

