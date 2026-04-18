<?php

namespace App\Modules\Produto\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Produto\Models\Produto;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProdutoController extends BaseApiController
{
    /**
     * Listar produtos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $query = Produto::where('empresa_id', $empresa->id);
            
            // Filtros
            if ($request->has('ativo')) {
                $query->where('ativo', $request->boolean('ativo'));
            } else {
                $query->ativos(); // Por padrão, apenas ativos
            }
            
            if ($request->has('categoria')) {
                $query->porCategoria($request->categoria);
            }
            
            if ($request->has('buscar')) {
                $query->buscar($request->buscar);
            }
            
            // Paginação
            $perPage = $request->get('per_page', 15);
            $produtos = $query->orderBy('nome')->paginate($perPage);
            
            return response()->json([
                'data' => $produtos->items(),
                'meta' => [
                    'current_page' => $produtos->currentPage(),
                    'last_page' => $produtos->lastPage(),
                    'per_page' => $produtos->perPage(),
                    'total' => $produtos->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar produtos');
        }
    }

    /**
     * Buscar produto específico
     */
    public function show(int $id): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $produto = Produto::where('empresa_id', $empresa->id)
                ->findOrFail($id);
            
            return response()->json(['data' => $produto]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar produto');
        }
    }

    /**
     * Criar produto
     */
    public function store(Request $request): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar produtos.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $validator = Validator::make($request->all(), [
                'codigo' => 'nullable|string|max:255',
                'nome' => 'required|string|max:255',
                'unidade' => 'required|string|max:10',
                'descricao' => 'nullable|string',
                'especificacao_tecnica' => 'nullable|string',
                'marca_modelo_referencia' => 'nullable|string|max:255',
                'categoria' => 'nullable|string|max:255',
                'valor_estimado_padrao' => 'nullable|numeric|min:0',
                'ativo' => 'boolean',
                'observacoes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $produto = Produto::create([
                'empresa_id' => $empresa->id,
                ...$validator->validated(),
                'ativo' => $request->get('ativo', true),
            ]);

            return response()->json([
                'message' => 'Produto criado com sucesso',
                'data' => $produto,
            ], 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar produto');
        }
    }

    /**
     * Atualizar produto
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar produtos.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $produto = Produto::where('empresa_id', $empresa->id)
                ->findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'codigo' => 'nullable|string|max:255',
                'nome' => 'sometimes|required|string|max:255',
                'unidade' => 'sometimes|required|string|max:10',
                'descricao' => 'nullable|string',
                'especificacao_tecnica' => 'nullable|string',
                'marca_modelo_referencia' => 'nullable|string|max:255',
                'categoria' => 'nullable|string|max:255',
                'valor_estimado_padrao' => 'nullable|numeric|min:0',
                'ativo' => 'boolean',
                'observacoes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $produto->update($validator->validated());

            return response()->json([
                'message' => 'Produto atualizado com sucesso',
                'data' => $produto->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar produto');
        }
    }

    /**
     * Deletar produto (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir produtos.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $produto = Produto::where('empresa_id', $empresa->id)
                ->findOrFail($id);
            
            $produto->delete();

            return response()->json([
                'message' => 'Produto excluído com sucesso',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir produto');
        }
    }
}

