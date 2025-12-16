<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustoIndireto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustoIndiretoController extends Controller
{
    /**
     * Lista todos os custos indiretos
     */
    public function index(Request $request)
    {
        $query = CustoIndireto::query();

        // Filtro por busca (descrição ou categoria)
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('descricao', 'ilike', '%' . $request->search . '%')
                  ->orWhere('categoria', 'ilike', '%' . $request->search . '%');
            });
        }

        // Filtro por data início
        if ($request->data_inicio) {
            $query->where('data', '>=', $request->data_inicio);
        }

        // Filtro por data fim
        if ($request->data_fim) {
            $query->where('data', '<=', $request->data_fim);
        }

        // Filtro por categoria
        if ($request->categoria) {
            $query->where('categoria', $request->categoria);
        }

        // Ordenação
        $query->orderBy('data', 'desc')->orderBy('created_at', 'desc');

        // Paginação
        $perPage = $request->per_page ?? 15;
        $custos = $query->paginate($perPage);

        return response()->json($custos);
    }

    /**
     * Cria um novo custo indireto
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descricao' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'categoria' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $custo = CustoIndireto::create($request->all());

        return response()->json([
            'message' => 'Custo indireto criado com sucesso',
            'data' => $custo
        ], 201);
    }

    /**
     * Exibe um custo indireto específico
     */
    public function show($id)
    {
        $custo = CustoIndireto::findOrFail($id);

        return response()->json([
            'data' => $custo
        ]);
    }

    /**
     * Atualiza um custo indireto
     */
    public function update(Request $request, $id)
    {
        $custo = CustoIndireto::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'descricao' => 'required|string|max:255',
            'data' => 'required|date',
            'valor' => 'required|numeric|min:0',
            'categoria' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $custo->update($request->all());

        return response()->json([
            'message' => 'Custo indireto atualizado com sucesso',
            'data' => $custo
        ]);
    }

    /**
     * Remove um custo indireto
     */
    public function destroy($id)
    {
        $custo = CustoIndireto::findOrFail($id);
        $custo->delete();

        return response()->json([
            'message' => 'Custo indireto removido com sucesso'
        ]);
    }

    /**
     * Retorna resumo de custos indiretos
     */
    public function resumo(Request $request)
    {
        $query = CustoIndireto::query();

        // Filtro por data início
        if ($request->data_inicio) {
            $query->where('data', '>=', $request->data_inicio);
        }

        // Filtro por data fim
        if ($request->data_fim) {
            $query->where('data', '<=', $request->data_fim);
        }

        $total = $query->sum('valor');
        $quantidade = $query->count();

        // Agrupar por categoria
        $porCategoria = $query->selectRaw('categoria, SUM(valor) as total')
            ->groupBy('categoria')
            ->get();

        return response()->json([
            'total' => round($total, 2),
            'quantidade' => $quantidade,
            'por_categoria' => $porCategoria,
        ]);
    }
}


