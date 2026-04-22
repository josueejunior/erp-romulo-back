<?php

namespace App\Modules\Processo\Controllers;
use App\Domain\Exceptions\DomainException;

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
use App\Helpers\PermissionHelper;
use App\Services\Pncp\PncpCompraIdentificador;
use App\Services\Pncp\PncpCompraParaProcessoMapper;
use App\Services\Pncp\PncpConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

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
        } catch (DomainException $e) {
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
            
            // Buscar modelo Eloquent para serialização com relacionamentos usados no detalhe do item.
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, [
                'fornecedor',
                'transportadora',
                'orcamentos.fornecedor',
                'orcamentos.formacaoPreco',
                'orcamentos.itens.formacaoPreco',
                'orcamentos.itens.processoItem',
                'orcamentoItens.formacaoPreco',
                'formacoesPreco.orcamento',
                'formacoesPreco.orcamentoItem',
            ]);

            if (!$itemModel) {
                return response()->json(['message' => 'Erro ao buscar item'], 500);
            }

            // Expor formação ativa explicitamente para a tela de formação de preço.
            // Prioriza accessor do item e fallback para o orçamento_item escolhido carregado na relação.
            $formacaoPrecoAtiva = $itemModel->formacaoPrecoAtiva;
            if (!$formacaoPrecoAtiva) {
                $orcamentoEscolhido = $itemModel->orcamentos
                    ?->first(fn($orcamento) => collect($orcamento->itens)->contains('fornecedor_escolhido', true));
                if ($orcamentoEscolhido) {
                    $itemEscolhido = collect($orcamentoEscolhido->itens)->firstWhere('fornecedor_escolhido', true);
                    $formacaoPrecoAtiva = $itemEscolhido?->formacaoPreco;
                }
            }
            $itemModel->setAttribute('formacao_preco_ativa', $formacaoPrecoAtiva);

            return response()->json(['data' => $itemModel]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (DomainException $e) {
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
        $empresaId = null;
        $tenantId = tenancy()->tenant?->id;
        $processoId = null;
        
        try {
            $route = $request->route();
            $processoId = (int) $route->parameter('processo');
            
            if (!$processoId) {
                \Log::warning('ProcessoItemController::store() - Processo não fornecido', [
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                ]);
                return response()->json(['message' => 'Processo não fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            $empresaId = $empresa->id;
            
            \Log::info('ProcessoItemController::store() - INÍCIO', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'processo_id' => $processoId,
                'request_keys' => array_keys($request->all()),
                'validated_keys' => array_keys($request->validated()),
            ]);
            
            // O Request já está validado via FormRequest
            // Criar DTO a partir dos dados validados
            $validatedData = $request->validated();
            
            \Log::debug('ProcessoItemController::store() - Dados validados', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'numero_item' => $validatedData['numero_item'] ?? null,
                'quantidade' => $validatedData['quantidade'] ?? null,
                'unidade' => $validatedData['unidade'] ?? null,
                'has_especificacao' => !empty($validatedData['especificacao_tecnica'] ?? null),
            ]);
            
            $dto = CriarProcessoItemDTO::fromArray($validatedData, $processoId, $empresa->id);
            
            \Log::debug('ProcessoItemController::store() - DTO criado, executando Use Case', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
            ]);
            
            // Executar Use Case (retorna entidade de domínio)
            $itemDomain = $this->criarProcessoItemUseCase->executar($dto);
            
            \Log::info('ProcessoItemController::store() - Item criado com sucesso', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'item_id' => $itemDomain->id,
            ]);
            
            // Buscar modelo Eloquent para serialização
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);

            if (!$itemModel) {
                \Log::error('ProcessoItemController::store() - Item criado mas não encontrado no repositório', [
                    'empresa_id' => $empresaId,
                    'tenant_id' => $tenantId,
                    'processo_id' => $processoId,
                    'item_domain_id' => $itemDomain->id,
                ]);
                return response()->json(['message' => 'Erro ao buscar item criado'], 500);
            }

            \Log::debug('ProcessoItemController::store() - Item serializado com sucesso', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'item_id' => $itemModel->id,
            ]);

            return response()->json(['data' => $itemModel], 201);
        } catch (NotFoundException $e) {
            \Log::warning('ProcessoItemController::store() - Processo não encontrado', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ProcessoEmExecucaoException $e) {
            \Log::warning('ProcessoItemController::store() - Processo em execução', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            \Log::warning('ProcessoItemController::store() - Erro de domínio', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('ProcessoItemController::store() - Erro ao criar item de processo', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data_keys' => array_keys($request->all()),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Compatibilidade para itens temporarios vindos de oportunidade.
     * Alguns fluxos enviam PUT /processos/{processo}/itens/{alias_temporario}
     * (ex: frem_opp_1). Nesse caso fazemos criacao do item.
     */
    public function upsertFromOportunidade(Request $request, $processo, string $itemAlias)
    {
        $empresaId = null;
        $tenantId = tenancy()->tenant?->id;
        $processoId = (int) $processo;

        try {
            if (!$processoId) {
                return response()->json(['message' => 'Processo nao fornecido'], 400);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            $empresaId = $empresa->id;

            // Normalizar payload para o formato de criacao de item
            $payload = [
                'fornecedor_id' => $request->input('fornecedor_id'),
                'transportadora_id' => $request->input('transportadora_id'),
                'numero_item' => $request->input('numero_item', $request->input('numero')),
                'codigo_interno' => $request->input('codigo_interno', $request->input('codigo')),
                'quantidade' => $request->input('quantidade', $request->input('qtd')),
                'unidade' => $request->input('unidade', $request->input('unidade_medida')),
                'especificacao_tecnica' => $request->input(
                    'especificacao_tecnica',
                    $request->input('descricao_item', $request->input('descricao'))
                ),
                'marca_modelo_referencia' => $request->input('marca_modelo_referencia'),
                'observacoes_edital' => $request->input('observacoes_edital'),
                'exige_atestado' => $request->input('exige_atestado'),
                'quantidade_minima_atestado' => $request->input('quantidade_minima_atestado'),
                'quantidade_atestado_cap_tecnica' => $request->input('quantidade_atestado_cap_tecnica'),
                'valor_estimado' => $request->input('valor_estimado'),
                'observacoes' => $request->input('observacoes'),
            ];

            $validator = Validator::make($payload, (new ProcessoItemCreateRequest())->rules());

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validacao ao criar item temporario de oportunidade.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dto = CriarProcessoItemDTO::fromArray($validator->validated(), $processoId, $empresa->id);
            $itemDomain = $this->criarProcessoItemUseCase->executar($dto);
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemDomain->id, ['fornecedor', 'transportadora']);

            if (!$itemModel) {
                return response()->json(['message' => 'Erro ao buscar item criado'], 500);
            }

            return response()->json([
                'data' => $itemModel,
                'meta' => [
                    'item_temporario' => $itemAlias,
                    'acao' => 'criado_via_put_compatibilidade',
                ],
            ]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ProcessoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('ProcessoItemController::upsertFromOportunidade() - Erro', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'processo_id' => $processoId,
                'item_alias' => $itemAlias,
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
    public function update(ProcessoItemUpdateRequest $request, $processo, $item)
    {
        try {
            $processoId = (int) $processo;
            $itemId = (int) $item;
            
            // Fallback caso venha via route parameter mas não via injeção direta (precaução)
            if (!$processoId) {
                $processoId = (int) $request->route('processo');
            }
            if (!$itemId) {
                $itemId = (int) $request->route('item');
            }
            
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar item de processo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId ?? null,
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
        } catch (DomainException $e) {
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
                'valor_final_sessao' => 'required|numeric|min:0',
            ]);

            $item->update([
                'valor_final_sessao' => $request->valor_final_sessao,
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
                'valor_negociado' => 'required|numeric|min:0',
            ]);

            $item->update([
                'valor_negociado' => $request->valor_negociado,
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /processos/{processo}/itens/{item}/pncp-referencia-formacao
     *
     * Cruza o {@code numero_item} local com a lista pública de itens da compra no PNCP
     * (mesmos parâmetros de {@see ProcessoController::pncpCompraParaFormulario}: referência ou cnpj+ano+sequencial).
     *
     * @see https://pncp.gov.br/api/consulta/swagger-ui/index.html
     */
    public function pncpReferenciaFormacaoPreco(Request $request, Processo $processo, ProcessoItem $item): JsonResponse
    {
        if (! PermissionHelper::canCreateProcess() && ! PermissionHelper::canEditProcess()) {
            return response()->json(['message' => 'Sem permissão para consultar o PNCP.'], 403);
        }

        $validator = Validator::make($request->query(), [
            'referencia' => ['nullable', 'string', 'max:8192'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'ano' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'sequencial' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->itemService->validarProcessoEmpresa($processo, $empresa->id);
            $this->itemService->validarItemEmpresa($item, $empresa->id);
            $this->itemService->validarItemPertenceProcesso($item, $processo);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $ids = PncpCompraIdentificador::fromQueryParams($validator->validated());
        if ($ids === null) {
            return response()->json([
                'success' => false,
                'message' => 'Informe o número de controle PNCP (ou link) ou CNPJ + ano + sequencial da compra.',
            ], 422);
        }

        try {
            $svc = PncpConsultaService::fromConfig();
            $compra = $svc->recuperarCompra($ids['cnpj'], $ids['ano'], $ids['sequencial']);
            $itensRows = $svc->listarItensCompra($ids['cnpj'], $ids['ano'], $ids['sequencial']);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        if ($compra === []) {
            return response()->json([
                'success' => false,
                'message' => 'Compra não encontrada no PNCP.',
            ], 404);
        }

        $numeroItemLocal = (int) $item->numero_item;
        $row = PncpCompraParaProcessoMapper::encontrarItemPncpPorNumero($itensRows, $numeroItemLocal);
        $ref = $row !== null ? PncpCompraParaProcessoMapper::mapearReferenciaFormacaoPreco($row) : null;

        $numeroControle = isset($compra['numeroControlePNCP']) && is_string($compra['numeroControlePNCP'])
            ? $compra['numeroControlePNCP']
            : null;
        $objetoCompra = isset($compra['objetoCompra']) ? trim((string) $compra['objetoCompra']) : '';
        if (mb_strlen($objetoCompra) > 500) {
            $objetoCompra = mb_substr($objetoCompra, 0, 497).'…';
        }

        $avisoQuantidade = null;
        if ($ref !== null && $ref['quantidade'] !== null && (float) $item->quantidade > 0
            && abs($ref['quantidade'] - (float) $item->quantidade) > 0.0001) {
            $avisoQuantidade = 'A quantidade no PNCP difere da quantidade cadastrada no item do processo; o valor unitário estimado foi mantido como divulgado no PNCP.';
        }

        $sugestao = $ref !== null ? ($ref['valor_unitario_estimado'] ?? null) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'pncp_ids' => $ids,
                'compra_resumo' => [
                    'numero_controle_pncp' => $numeroControle,
                    'objeto_compra' => $objetoCompra !== '' ? $objetoCompra : null,
                ],
                'processo_item' => [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'quantidade' => (float) $item->quantidade,
                    'unidade' => $item->unidade,
                    'valor_estimado' => $item->valor_estimado !== null ? (float) $item->valor_estimado : null,
                    'fonte_valor' => $item->fonte_valor,
                ],
                'pncp_item' => $ref !== null
                    ? array_merge(['encontrado' => true], $ref)
                    : [
                        'encontrado' => false,
                        'numeros_itens_pn_disponiveis' => PncpCompraParaProcessoMapper::listarNumerosItensPncp($itensRows),
                    ],
                'sugestao_custo_produto' => $sugestao,
                'aviso' => $avisoQuantidade,
            ],
        ]);
    }
}
