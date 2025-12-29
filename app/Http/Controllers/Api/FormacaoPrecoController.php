<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\FormacaoPreco\UseCases\CriarFormacaoPrecoUseCase;
use App\Application\FormacaoPreco\DTOs\CriarFormacaoPrecoDTO;
use App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class FormacaoPrecoController extends Controller
{
    public function __construct(
        private CriarFormacaoPrecoUseCase $criarFormacaoUseCase,
        private FormacaoPrecoRepositoryInterface $formacaoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'processo_item_id' => 'nullable|integer|exists:processo_itens,id',
                'orcamento_id' => 'nullable|integer|exists:orcamentos,id',
                'orcamento_item_id' => 'nullable|integer|exists:orcamento_itens,id',
                'custo_produto' => 'nullable|numeric|min:0',
                'frete' => 'nullable|numeric|min:0',
                'percentual_impostos' => 'nullable|numeric|min:0',
                'valor_impostos' => 'nullable|numeric|min:0',
                'percentual_margem' => 'nullable|numeric|min:0',
                'valor_margem' => 'nullable|numeric|min:0',
                'preco_minimo' => 'nullable|numeric|min:0',
                'preco_recomendado' => 'nullable|numeric|min:0',
                'observacoes' => 'nullable|string',
            ]);

            $dto = CriarFormacaoPrecoDTO::fromArray($validated);
            $formacao = $this->criarFormacaoUseCase->executar($dto);

            return response()->json([
                'message' => 'Formação de preço criada com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $formacao->id,
                    'preco_recomendado' => $formacao->precoRecomendado,
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
            Log::error('Erro ao criar formação de preço', [
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
            $filtros = $request->only(['processo_item_id', 'orcamento_id', 'per_page']);
            $formacoes = $this->formacaoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $formacoes->items(),
                'pagination' => [
                    'current_page' => $formacoes->currentPage(),
                    'per_page' => $formacoes->perPage(),
                    'total' => $formacoes->total(),
                    'last_page' => $formacoes->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar formações de preço', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar formações de preço.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $formacao = $this->formacaoRepository->buscarPorId($id);

            if (!$formacao) {
                return response()->json(['message' => 'Formação de preço não encontrada.'], 404);
            }

            return response()->json(['data' => [
                'id' => $formacao->id,
                'preco_recomendado' => $formacao->precoRecomendado,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar formação de preço', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar formação de preço.'], 500);
        }
    }
}

