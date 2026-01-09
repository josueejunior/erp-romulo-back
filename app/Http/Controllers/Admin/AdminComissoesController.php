<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AfiliadoComissaoRecorrente;
use App\Models\AfiliadoPagamentoComissao;
use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Controller Admin para gerenciar comissões de afiliados
 */
class AdminComissoesController extends Controller
{
    /**
     * Lista todas as comissões (com filtros)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AfiliadoComissaoRecorrente::with(['afiliado', 'indicacao'])
                ->orderBy('data_inicio_ciclo', 'desc');

            // Filtros
            if ($request->has('afiliado_id')) {
                $query->where('afiliado_id', $request->input('afiliado_id'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('data_inicio')) {
                $query->where('data_inicio_ciclo', '>=', $request->input('data_inicio'));
            }

            if ($request->has('data_fim')) {
                $query->where('data_inicio_ciclo', '<=', $request->input('data_fim'));
            }

            $perPage = $request->input('per_page', 15);
            $comissoes = $query->paginate($perPage);

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
            $comissao = AfiliadoComissaoRecorrente::find($comissaoId);

            if (!$comissao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comissão não encontrada.',
                ], 404);
            }

            $comissao->update([
                'status' => 'paga',
                'data_pagamento_afiliado' => $request->input('data_pagamento', Carbon::now()),
                'observacoes' => $request->input('observacoes'),
            ]);

            Log::info('Comissão marcada como paga', [
                'comissao_id' => $comissaoId,
                'afiliado_id' => $comissao->afiliado_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comissão marcada como paga.',
                'data' => $comissao->fresh(),
            ]);

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
            $request->validate([
                'afiliado_id' => 'required|exists:afiliados,id',
                'periodo_competencia' => 'required|date',
                'comissao_ids' => 'required|array',
                'comissao_ids.*' => 'exists:afiliado_comissoes_recorrentes,id',
                'metodo_pagamento' => 'nullable|string|max:50',
                'comprovante' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
            ]);

            return DB::transaction(function () use ($request) {
                // Buscar comissões
                $comissoes = AfiliadoComissaoRecorrente::whereIn('id', $request->input('comissao_ids'))
                    ->where('afiliado_id', $request->input('afiliado_id'))
                    ->where('status', 'pendente')
                    ->get();

                if ($comissoes->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nenhuma comissão pendente encontrada.',
                    ], 400);
                }

                // Calcular valor total
                $valorTotal = $comissoes->sum('valor_comissao');

                // Criar pagamento
                $pagamento = AfiliadoPagamentoComissao::create([
                    'afiliado_id' => $request->input('afiliado_id'),
                    'periodo_competencia' => $request->input('periodo_competencia'),
                    'data_pagamento' => $request->input('data_pagamento', Carbon::now()),
                    'valor_total' => $valorTotal,
                    'quantidade_comissoes' => $comissoes->count(),
                    'status' => 'pago',
                    'metodo_pagamento' => $request->input('metodo_pagamento'),
                    'comprovante' => $request->input('comprovante'),
                    'observacoes' => $request->input('observacoes'),
                    'pago_por' => auth('admin')->id(),
                    'pago_em' => Carbon::now(),
                ]);

                // Marcar comissões como pagas
                foreach ($comissoes as $comissao) {
                    $comissao->update([
                        'status' => 'paga',
                        'data_pagamento_afiliado' => $pagamento->data_pagamento,
                    ]);
                }

                Log::info('Pagamento de comissões criado', [
                    'pagamento_id' => $pagamento->id,
                    'afiliado_id' => $request->input('afiliado_id'),
                    'valor_total' => $valorTotal,
                    'quantidade_comissoes' => $comissoes->count(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento criado com sucesso.',
                    'data' => $pagamento->load('afiliado'),
                ], 201);

            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
            ], 422);

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
            $query = AfiliadoPagamentoComissao::with('afiliado')
                ->orderBy('periodo_competencia', 'desc');

            if ($request->has('afiliado_id')) {
                $query->where('afiliado_id', $request->input('afiliado_id'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            $perPage = $request->input('per_page', 15);
            $pagamentos = $query->paginate($perPage);

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

