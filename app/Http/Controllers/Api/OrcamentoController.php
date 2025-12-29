<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Orcamento\UseCases\CriarOrcamentoUseCase;
use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class OrcamentoController extends Controller
{
    public function __construct(
        private CriarOrcamentoUseCase $criarOrcamentoUseCase,
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'processo_id' => 'nullable|integer|exists:processos,id',
                'processo_item_id' => 'nullable|integer|exists:processo_itens,id',
                'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
                'transportadora_id' => 'nullable|integer|exists:transportadoras,id',
                'custo_produto' => 'nullable|numeric|min:0',
                'marca_modelo' => 'nullable|string|max:255',
                'ajustes_especificacao' => 'nullable|string',
                'frete' => 'nullable|numeric|min:0',
                'frete_incluido' => 'nullable|boolean',
                'fornecedor_escolhido' => 'nullable|boolean',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
            ]);

            $dto = CriarOrcamentoDTO::fromArray($validated);
            $orcamento = $this->criarOrcamentoUseCase->executar($dto);

            return response()->json([
                'message' => 'Orçamento criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $orcamento->id,
                    'custo_total' => $orcamento->calcularCustoTotal(),
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
            Log::error('Erro ao criar orçamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a solicitação.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $filtros = $request->only(['empresa_id', 'processo_id', 'per_page']);
            $orcamentos = $this->orcamentoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $orcamentos->items(),
                'pagination' => [
                    'current_page' => $orcamentos->currentPage(),
                    'per_page' => $orcamentos->perPage(),
                    'total' => $orcamentos->total(),
                    'last_page' => $orcamentos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar orçamentos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar orçamentos.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $orcamento = $this->orcamentoRepository->buscarPorId($id);

            if (!$orcamento) {
                return response()->json(['message' => 'Orçamento não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $orcamento->id,
                'custo_total' => $orcamento->calcularCustoTotal(),
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar orçamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar orçamento.'], 500);
        }
    }
}

