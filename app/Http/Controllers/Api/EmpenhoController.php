<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Empenho\UseCases\CriarEmpenhoUseCase;
use App\Application\Empenho\UseCases\ConcluirEmpenhoUseCase;
use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class EmpenhoController extends Controller
{
    public function __construct(
        private CriarEmpenhoUseCase $criarEmpenhoUseCase,
        private ConcluirEmpenhoUseCase $concluirEmpenhoUseCase,
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'processo_id' => 'nullable|integer|exists:processos,id',
                'contrato_id' => 'nullable|integer|exists:contratos,id',
                'autorizacao_fornecimento_id' => 'nullable|integer|exists:autorizacoes_fornecimento,id',
                'numero' => 'nullable|string|max:255',
                'data' => 'nullable|date',
                'data_recebimento' => 'nullable|date',
                'prazo_entrega_calculado' => 'nullable|date',
                'valor' => 'required|numeric|min:0',
                'situacao' => 'nullable|string',
                'observacoes' => 'nullable|string',
                'numero_cte' => 'nullable|string|max:255',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'valor.required' => 'O valor é obrigatório.',
            ]);

            $dto = CriarEmpenhoDTO::fromArray($validated);
            $empenho = $this->criarEmpenhoUseCase->executar($dto);

            return response()->json([
                'message' => 'Empenho criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $empenho->id,
                    'numero' => $empenho->numero,
                    'valor' => $empenho->valor,
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
            Log::error('Erro ao criar empenho', [
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

    public function concluir(Request $request, $id)
    {
        try {
            $empenho = $this->concluirEmpenhoUseCase->executar($id);

            return response()->json([
                'message' => 'Empenho concluído com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $empenho->id,
                    'concluido' => $empenho->concluido,
                    'situacao' => $empenho->situacao,
                ],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao concluir empenho', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao concluir empenho.'], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $filtros = $request->only(['empresa_id', 'contrato_id', 'per_page']);
            $empenhos = $this->empenhoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $empenhos->items(),
                'pagination' => [
                    'current_page' => $empenhos->currentPage(),
                    'per_page' => $empenhos->perPage(),
                    'total' => $empenhos->total(),
                    'last_page' => $empenhos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empenhos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empenhos.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $empenho = $this->empenhoRepository->buscarPorId($id);

            if (!$empenho) {
                return response()->json(['message' => 'Empenho não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $empenho->id,
                'numero' => $empenho->numero,
                'valor' => $empenho->valor,
                'concluido' => $empenho->concluido,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar empenho', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar empenho.'], 500);
        }
    }
}

