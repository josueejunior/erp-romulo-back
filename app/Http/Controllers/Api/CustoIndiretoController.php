<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\CustoIndireto\UseCases\CriarCustoIndiretoUseCase;
use App\Application\CustoIndireto\DTOs\CriarCustoIndiretoDTO;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class CustoIndiretoController extends Controller
{
    public function __construct(
        private CriarCustoIndiretoUseCase $criarCustoUseCase,
        private CustoIndiretoRepositoryInterface $custoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'descricao' => 'required|string|max:255',
                'data' => 'nullable|date',
                'valor' => 'required|numeric|min:0',
                'categoria' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'descricao.required' => 'A descrição é obrigatória.',
                'valor.required' => 'O valor é obrigatório.',
            ]);

            $dto = CriarCustoIndiretoDTO::fromArray($validated);
            $custo = $this->criarCustoUseCase->executar($dto);

            return response()->json([
                'message' => 'Custo indireto criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $custo->id,
                    'descricao' => $custo->descricao,
                    'valor' => $custo->valor,
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
            Log::error('Erro ao criar custo indireto', [
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
            $filtros = $request->only(['empresa_id', 'categoria', 'per_page']);
            $custos = $this->custoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $custos->items(),
                'pagination' => [
                    'current_page' => $custos->currentPage(),
                    'per_page' => $custos->perPage(),
                    'total' => $custos->total(),
                    'last_page' => $custos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar custos indiretos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar custos indiretos.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $custo = $this->custoRepository->buscarPorId($id);

            if (!$custo) {
                return response()->json(['message' => 'Custo indireto não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $custo->id,
                'descricao' => $custo->descricao,
                'valor' => $custo->valor,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar custo indireto', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar custo indireto.'], 500);
        }
    }
}

