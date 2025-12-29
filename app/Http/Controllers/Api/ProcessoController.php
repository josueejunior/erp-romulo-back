<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Processo\UseCases\CriarProcessoUseCase;
use App\Application\Processo\UseCases\MoverParaJulgamentoUseCase;
use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 * NÃO tem regra de negócio
 */
class ProcessoController extends Controller
{
    public function __construct(
        private CriarProcessoUseCase $criarProcessoUseCase,
        private MoverParaJulgamentoUseCase $moverParaJulgamentoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Criar um novo processo
     */
    public function store(Request $request)
    {
        try {
            // Validação básica (apenas formato dos dados)
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'orgao_id' => 'nullable|integer|exists:orgaos,id',
                'setor_id' => 'nullable|integer|exists:setors,id',
                'modalidade' => 'nullable|string|max:255',
                'numero_modalidade' => 'nullable|string|max:255',
                'numero_processo_administrativo' => 'nullable|string|max:255',
                'link_edital' => 'nullable|url|max:500',
                'portal' => 'nullable|string|max:255',
                'numero_edital' => 'nullable|string|max:255',
                'srp' => 'nullable|boolean',
                'objeto_resumido' => 'nullable|string|max:500',
                'data_hora_sessao_publica' => 'nullable|date',
                'horario_sessao_publica' => 'nullable|date',
                'endereco_entrega' => 'nullable|string|max:500',
                'local_entrega_detalhado' => 'nullable|string|max:500',
                'forma_entrega' => 'nullable|string|max:255',
                'prazo_entrega' => 'nullable|string|max:255',
                'forma_prazo_entrega' => 'nullable|string|max:255',
                'prazos_detalhados' => 'nullable|string',
                'prazo_pagamento' => 'nullable|string|max:255',
                'validade_proposta' => 'nullable|string|max:255',
                'validade_proposta_inicio' => 'nullable|date',
                'validade_proposta_fim' => 'nullable|date',
                'tipo_selecao_fornecedor' => 'nullable|string|max:255',
                'tipo_disputa' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:rascunho,publicado,em_disputa,julgamento,execucao,vencido,arquivado',
                'status_participacao' => 'nullable|string|max:255',
                'data_recebimento_pagamento' => 'nullable|date',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'empresa_id.exists' => 'A empresa selecionada não existe.',
            ]);

            // Criar DTO
            $dto = CriarProcessoDTO::fromArray($validated);

            // Executar Use Case (aqui está a lógica)
            $processo = $this->criarProcessoUseCase->executar($dto);

            return response()->json([
                'message' => 'Processo criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $processo->id,
                    'numero_modalidade' => $processo->numeroModalidade,
                    'status' => $processo->status,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao criar processo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a solicitação. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    /**
     * Listar processos
     */
    public function index(Request $request)
    {
        try {
            $filtros = $request->only(['empresa_id', 'status', 'search', 'per_page']);
            $processos = $this->processoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $processos->items(),
                'pagination' => [
                    'current_page' => $processos->currentPage(),
                    'per_page' => $processos->perPage(),
                    'total' => $processos->total(),
                    'last_page' => $processos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar processos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao listar processos.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mostrar processo específico
     */
    public function show($id)
    {
        try {
            $processo = $this->processoRepository->buscarPorId($id);

            if (!$processo) {
                return response()->json([
                    'message' => 'Processo não encontrado.',
                ], 404);
            }

            return response()->json([
                'data' => [
                    'id' => $processo->id,
                    'numero_modalidade' => $processo->numeroModalidade,
                    'status' => $processo->status,
                    'empresa_id' => $processo->empresaId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao buscar processo.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mover processo para julgamento
     */
    public function moverParaJulgamento(Request $request, $id)
    {
        try {
            $processo = $this->moverParaJulgamentoUseCase->executar($id);

            return response()->json([
                'message' => 'Processo movido para julgamento com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $processo->id,
                    'status' => $processo->status,
                ],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao mover processo para julgamento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a solicitação.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }
}

