<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class NotaFiscalController extends Controller
{
    public function __construct(
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'processo_id' => 'nullable|integer|exists:processos,id',
                'empenho_id' => 'nullable|integer|exists:empenhos,id',
                'contrato_id' => 'nullable|integer|exists:contratos,id',
                'autorizacao_fornecimento_id' => 'nullable|integer|exists:autorizacoes_fornecimento,id',
                'tipo' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:255',
                'serie' => 'nullable|string|max:20',
                'data_emissao' => 'nullable|date',
                'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
                'transportadora' => 'nullable|string|max:255',
                'numero_cte' => 'nullable|string|max:255',
                'data_entrega_prevista' => 'nullable|date',
                'data_entrega_realizada' => 'nullable|date',
                'situacao_logistica' => 'nullable|string|max:255',
                'valor' => 'required|numeric|min:0',
                'custo_produto' => 'nullable|numeric|min:0',
                'custo_frete' => 'nullable|numeric|min:0',
                'comprovante_pagamento' => 'nullable|string|max:500',
                'arquivo' => 'nullable|string|max:500',
                'situacao' => 'nullable|string|max:255',
                'data_pagamento' => 'nullable|date',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'valor.required' => 'O valor é obrigatório.',
            ]);

            $dto = CriarNotaFiscalDTO::fromArray($validated);
            $notaFiscal = $this->criarNotaFiscalUseCase->executar($dto);

            return response()->json([
                'message' => 'Nota fiscal criada com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $notaFiscal->id,
                    'numero' => $notaFiscal->numero,
                    'valor' => $notaFiscal->valor,
                    'custo_total' => $notaFiscal->custoTotal,
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
            Log::error('Erro ao criar nota fiscal', [
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
            $filtros = $request->only(['empresa_id', 'empenho_id', 'per_page']);
            $notasFiscais = $this->notaFiscalRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $notasFiscais->items(),
                'pagination' => [
                    'current_page' => $notasFiscais->currentPage(),
                    'per_page' => $notasFiscais->perPage(),
                    'total' => $notasFiscais->total(),
                    'last_page' => $notasFiscais->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar notas fiscais', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar notas fiscais.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $notaFiscal = $this->notaFiscalRepository->buscarPorId($id);

            if (!$notaFiscal) {
                return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
            }

            return response()->json(['data' => [
                'id' => $notaFiscal->id,
                'numero' => $notaFiscal->numero,
                'valor' => $notaFiscal->valor,
                'custo_total' => $notaFiscal->custoTotal,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar nota fiscal', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar nota fiscal.'], 500);
        }
    }
}

