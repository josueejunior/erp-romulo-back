<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Afiliado\UseCases\ListarComissoesAdminUseCase;
use App\Application\Afiliado\UseCases\MarcarComissaoComoPagaUseCase;
use App\Application\Afiliado\UseCases\CriarPagamentoComissaoUseCase;
use App\Application\Afiliado\UseCases\ListarPagamentosComissaoUseCase;
use App\Application\Afiliado\DTOs\CriarPagamentoComissaoDTO;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller Admin para gerenciar comissões de afiliados
 * 
 * ✅ DDD: Usa Use Cases e DTOs
 */
class AdminComissoesController extends Controller
{
    public function __construct(
        private readonly ListarComissoesAdminUseCase $listarComissoesAdminUseCase,
        private readonly MarcarComissaoComoPagaUseCase $marcarComissaoComoPagaUseCase,
        private readonly CriarPagamentoComissaoUseCase $criarPagamentoComissaoUseCase,
        private readonly ListarPagamentosComissaoUseCase $listarPagamentosComissaoUseCase,
    ) {}
    /**
     * Lista todas as comissões (com filtros)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $comissoes = $this->listarComissoesAdminUseCase->executar(
                afiliadoId: $request->input('afiliado_id') ? (int) $request->input('afiliado_id') : null,
                status: $request->input('status'),
                dataInicio: $request->input('data_inicio'),
                dataFim: $request->input('data_fim'),
                perPage: (int) $request->input('per_page', 15),
                page: (int) $request->input('page', 1),
            );

            return response()->json([
                'success' => true,
                'data' => $comissoes,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao listar comissões', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar comissões.',
            ], 500);
        }
    }

    /**
     * Marca comissão como paga
     */
    public function marcarComoPaga(Request $request, int $comissaoId): JsonResponse
    {
        try {
            $comissao = $this->marcarComissaoComoPagaUseCase->executar(
                comissaoId: $comissaoId,
                dataPagamento: $request->input('data_pagamento'),
                observacoes: $request->input('observacoes'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Comissão marcada como paga.',
                'data' => $comissao,
            ]);

        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            Log::error('Erro ao marcar comissão como paga', [
                'comissao_id' => $comissaoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar comissão como paga.',
            ], 500);
        }
    }

    /**
     * Cria pagamento de comissões (agrupa múltiplas comissões)
     */
    public function criarPagamento(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'afiliado_id' => 'required|exists:afiliados,id',
                'periodo_competencia' => 'required|date',
                'comissao_ids' => 'required|array',
                'comissao_ids.*' => 'exists:afiliado_comissoes_recorrentes,id',
                'metodo_pagamento' => 'nullable|string|max:50',
                'comprovante' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
                'data_pagamento' => 'nullable|date',
            ]);

            $dto = CriarPagamentoComissaoDTO::fromArray($validated);
            $pagoPor = auth('admin')->id();

            $pagamento = $this->criarPagamentoComissaoUseCase->executar($dto, $pagoPor);

            return response()->json([
                'success' => true,
                'message' => 'Pagamento criado com sucesso.',
                'data' => $pagamento,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Erro ao criar pagamento de comissões', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar pagamento.',
            ], 500);
        }
    }

    /**
     * Lista pagamentos de comissões
     */
    public function pagamentos(Request $request): JsonResponse
    {
        try {
            $pagamentos = $this->listarPagamentosComissaoUseCase->executar(
                afiliadoId: $request->input('afiliado_id') ? (int) $request->input('afiliado_id') : null,
                status: $request->input('status'),
                perPage: (int) $request->input('per_page', 15),
                page: (int) $request->input('page', 1),
            );

            return response()->json([
                'success' => true,
                'data' => $pagamentos,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao listar pagamentos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar pagamentos.',
            ], 500);
        }
    }
}

