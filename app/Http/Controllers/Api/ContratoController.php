<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Contrato\UseCases\CriarContratoUseCase;
use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class ContratoController extends Controller
{
    public function __construct(
        private CriarContratoUseCase $criarContratoUseCase,
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'processo_id' => 'nullable|integer|exists:processos,id',
                'numero' => 'nullable|string|max:255',
                'data_inicio' => 'nullable|date',
                'data_fim' => 'nullable|date',
                'data_assinatura' => 'nullable|date',
                'valor_total' => 'required|numeric|min:0',
                'condicoes_comerciais' => 'nullable|string',
                'condicoes_tecnicas' => 'nullable|string',
                'locais_entrega' => 'nullable|string',
                'prazos_contrato' => 'nullable|string',
                'regras_contrato' => 'nullable|string',
                'situacao' => 'nullable|string',
                'vigente' => 'nullable|boolean',
                'observacoes' => 'nullable|string',
                'arquivo_contrato' => 'nullable|string|max:500',
                'numero_cte' => 'nullable|string|max:255',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'valor_total.required' => 'O valor total é obrigatório.',
            ]);

            $dto = CriarContratoDTO::fromArray($validated);
            $contrato = $this->criarContratoUseCase->executar($dto);

            return response()->json([
                'message' => 'Contrato criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $contrato->id,
                    'numero' => $contrato->numero,
                    'valor_total' => $contrato->valorTotal,
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
            Log::error('Erro ao criar contrato', [
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
            $contratos = $this->contratoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $contratos->items(),
                'pagination' => [
                    'current_page' => $contratos->currentPage(),
                    'per_page' => $contratos->perPage(),
                    'total' => $contratos->total(),
                    'last_page' => $contratos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar contratos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar contratos.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $contrato = $this->contratoRepository->buscarPorId($id);

            if (!$contrato) {
                return response()->json(['message' => 'Contrato não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $contrato->id,
                'numero' => $contrato->numero,
                'valor_total' => $contrato->valorTotal,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar contrato', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar contrato.'], 500);
        }
    }
}

