<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\AutorizacaoFornecimento\UseCases\CriarAutorizacaoFornecimentoUseCase;
use App\Application\AutorizacaoFornecimento\DTOs\CriarAutorizacaoFornecimentoDTO;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class AutorizacaoFornecimentoController extends Controller
{
    public function __construct(
        private CriarAutorizacaoFornecimentoUseCase $criarAutorizacaoUseCase,
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'processo_id' => 'nullable|integer|exists:processos,id',
                'contrato_id' => 'nullable|integer|exists:contratos,id',
                'numero' => 'nullable|string|max:255',
                'data' => 'nullable|date',
                'data_adjudicacao' => 'nullable|date',
                'data_homologacao' => 'nullable|date',
                'data_fim_vigencia' => 'nullable|date',
                'condicoes_af' => 'nullable|string',
                'itens_arrematados' => 'nullable|string',
                'valor' => 'required|numeric|min:0',
                'situacao' => 'nullable|string|max:255',
                'situacao_detalhada' => 'nullable|string',
                'vigente' => 'nullable|boolean',
                'observacoes' => 'nullable|string',
                'numero_cte' => 'nullable|string|max:255',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'valor.required' => 'O valor é obrigatório.',
            ]);

            $dto = CriarAutorizacaoFornecimentoDTO::fromArray($validated);
            $autorizacao = $this->criarAutorizacaoUseCase->executar($dto);

            return response()->json([
                'message' => 'Autorização de fornecimento criada com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $autorizacao->id,
                    'numero' => $autorizacao->numero,
                    'valor' => $autorizacao->valor,
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
            Log::error('Erro ao criar autorização de fornecimento', [
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
            $filtros = $request->only(['empresa_id', 'processo_id', 'contrato_id', 'per_page']);
            $autorizacoes = $this->autorizacaoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $autorizacoes->items(),
                'pagination' => [
                    'current_page' => $autorizacoes->currentPage(),
                    'per_page' => $autorizacoes->perPage(),
                    'total' => $autorizacoes->total(),
                    'last_page' => $autorizacoes->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar autorizações de fornecimento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar autorizações de fornecimento.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $autorizacao = $this->autorizacaoRepository->buscarPorId($id);

            if (!$autorizacao) {
                return response()->json(['message' => 'Autorização de fornecimento não encontrada.'], 404);
            }

            return response()->json(['data' => [
                'id' => $autorizacao->id,
                'numero' => $autorizacao->numero,
                'valor' => $autorizacao->valor,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar autorização de fornecimento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar autorização de fornecimento.'], 500);
        }
    }
}

