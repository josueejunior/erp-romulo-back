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
        \Log::info('FormacaoPrecoController::store - MÉTODO CHAMADO', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'route_parameters' => $request->route()?->parameters(),
            'request_data' => $request->all(),
        ]);
        
        try {
            \Log::debug('FormacaoPrecoController::store - Iniciando', [
                'request_data' => $request->all(),
                'validated_data' => $request->validated(),
            ]);
            
            try {
                [$processo, $item, $orcamento] = $this->resolveContext($request);
            } catch (NotFoundException $e) {
                \Log::error('FormacaoPrecoController::store - Erro ao resolver contexto', [
                    'error' => $e->getMessage(),
                    'route_parameters' => $request->route()?->parameters(),
                ]);
                return response()->json(['message' => $e->getMessage()], 400);
            }
            
            \Log::debug('FormacaoPrecoController::store - Contexto resolvido', [
                'processo_id' => $processo->id,
                'item_id' => $item->id,
                'orcamento_id' => $orcamento->id,
            ]);
            
            // ✅ Segurança: Validar permissão (mesma regra da Web)
            $this->authorize('create', $processo);

            $empresaId = $this->getEmpresaAtivaOrFail()->id;

            // FormRequest já validou os dados
            $formacaoPreco = $this->formacaoPrecoService->store(
                $processo,
                $item,
                $orcamento,
                $request->validated(),
                $empresaId
            );

            \Log::debug('FormacaoPrecoController::store - Formação de preço criada', [
                'formacao_preco_id' => $formacaoPreco->id,
            ]);

            return new FormacaoPrecoResource($formacaoPreco);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('FormacaoPrecoController::store - Erro de validação', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            \Log::error('FormacaoPrecoController::store - Entidade não pertence', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (NotFoundException $e) {
            \Log::error('FormacaoPrecoController::store - Não encontrado', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            \Log::error('FormacaoPrecoController::store - DomainException', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('FormacaoPrecoController::store - Erro inesperado', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            return response()->json(['message' => 'Erro ao criar formação de preço'], 500);
        }
    }

    /**
     * API: Atualizar formação de preço (Route::module)
     * 
     * ✅ DDD: 
     * - Usa resolveContext para eliminar repetição
     * - FormRequest valida dados (Service assume válidos)
     * - ACL via authorize
     */
    public function update(FormacaoPrecoRequest $request): JsonResponse|FormacaoPrecoResource
    {
        try {
            [$processo, $item, $orcamento] = $this->resolveContext($request);
            $empresaId = $this->getEmpresaAtivaOrFail()->id;

            // Recuperar ID da rota com segurança
            $id = $request->route('formacao_preco') ?? $request->route('formacaoPreco') ?? $request->route('id');
            
            if (!$id) {
                 return response()->json(['message' => 'ID da formação de preço não fornecido'], 400);
            }

            $formacaoPreco = $this->formacaoPrecoRepository->buscarModeloPorId((int) $id);
            if (!$formacaoPreco) {
                throw new FormacaoPrecoNaoEncontradaException();
            }

            // ✅ Segurança: Validar permissão (mesma regra da Web)
            $this->authorize('update', $formacaoPreco);

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

