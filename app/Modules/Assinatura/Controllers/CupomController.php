<?php

namespace App\Modules\Assinatura\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Assinatura\Models\Cupom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CupomController extends BaseApiController
{
    /**
     * Validar cupom
     * GET /api/v1/cupons/{codigo}/validar
     */
    public function validar(Request $request, string $codigo): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plano_id' => 'required|integer|exists:planos,id',
                'valor_compra' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cupom = Cupom::where('codigo', strtoupper($codigo))->first();

            if (!$cupom) {
                return response()->json([
                    'valido' => false,
                    'motivo' => 'Cupom não encontrado'
                ], 404);
            }

            $tenant = $this->getTenantOrFail();
            $validated = $validator->validated();

            $validacao = $cupom->podeSerUsadoPor(
                $tenant->id,
                $validated['plano_id'],
                $validated['valor_compra']
            );

            if (!$validacao['valido']) {
                return response()->json($validacao, 400);
            }

            $valorDesconto = $cupom->calcularDesconto($validated['valor_compra']);
            $valorFinal = max(0, $validated['valor_compra'] - $valorDesconto);

            return response()->json([
                'valido' => true,
                'cupom' => [
                    'id' => $cupom->id,
                    'codigo' => $cupom->codigo,
                    'tipo' => $cupom->tipo,
                    'valor' => $cupom->valor,
                    'descricao' => $cupom->descricao,
                ],
                'desconto' => [
                    'valor_original' => $validated['valor_compra'],
                    'valor_desconto' => $valorDesconto,
                    'valor_final' => $valorFinal,
                    'percentual_desconto' => round(($valorDesconto / $validated['valor_compra']) * 100, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao validar cupom');
        }
    }

    /**
     * Listar cupons (admin)
     * GET /api/v1/admin/cupons
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Cupom::query();

            // Filtros
            if ($request->has('ativo')) {
                $query->where('ativo', $request->boolean('ativo'));
            }

            if ($request->has('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            $cupons = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json($cupons);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar cupons');
        }
    }

    /**
     * Criar cupom (admin)
     * POST /api/v1/admin/cupons
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo' => 'required|string|max:50|unique:cupons,codigo',
                'tipo' => 'required|in:percentual,valor_fixo',
                'valor' => 'required|numeric|min:0',
                'data_validade_inicio' => 'nullable|date',
                'data_validade_fim' => 'nullable|date|after_or_equal:data_validade_inicio',
                'limite_uso' => 'nullable|integer|min:1',
                'uso_unico_por_usuario' => 'boolean',
                'planos_permitidos' => 'nullable|array',
                'planos_permitidos.*' => 'integer|exists:planos,id',
                'valor_minimo_compra' => 'nullable|numeric|min:0',
                'descricao' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['codigo'] = strtoupper($data['codigo']);
            $data['ativo'] = true;
            $data['total_usado'] = 0;

            $cupom = Cupom::create($data);

            return response()->json([
                'message' => 'Cupom criado com sucesso',
                'data' => $cupom
            ], 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar cupom');
        }
    }

    /**
     * Atualizar cupom (admin)
     * PUT /api/v1/admin/cupons/{cupom}
     */
    public function update(Request $request, Cupom $cupom): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo' => 'sometimes|string|max:50|unique:cupons,codigo,' . $cupom->id,
                'tipo' => 'sometimes|in:percentual,valor_fixo',
                'valor' => 'sometimes|numeric|min:0',
                'data_validade_inicio' => 'nullable|date',
                'data_validade_fim' => 'nullable|date|after_or_equal:data_validade_inicio',
                'limite_uso' => 'nullable|integer|min:1',
                'uso_unico_por_usuario' => 'boolean',
                'planos_permitidos' => 'nullable|array',
                'planos_permitidos.*' => 'integer|exists:planos,id',
                'valor_minimo_compra' => 'nullable|numeric|min:0',
                'ativo' => 'boolean',
                'descricao' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            if (isset($data['codigo'])) {
                $data['codigo'] = strtoupper($data['codigo']);
            }

            $cupom->update($data);

            return response()->json([
                'message' => 'Cupom atualizado com sucesso',
                'data' => $cupom
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar cupom');
        }
    }

    /**
     * Deletar cupom (admin)
     * DELETE /api/v1/admin/cupons/{cupom}
     */
    public function destroy(Cupom $cupom): JsonResponse
    {
        try {
            $cupom->delete();

            return response()->json([
                'message' => 'Cupom deletado com sucesso'
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar cupom');
        }
    }
}
