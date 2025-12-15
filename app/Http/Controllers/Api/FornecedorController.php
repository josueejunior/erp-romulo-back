<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FornecedorResource;
use App\Models\Fornecedor;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Fornecedor::query();

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('razao_social', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%")
                  ->orWhere('nome_fantasia', 'like', "%{$request->search}%");
            });
        }

        if ($request->boolean('apenas_transportadoras')) {
            $query->where('is_transportadora', true);
        }

        $fornecedores = $query->orderBy('razao_social')->paginate(15);

        return FornecedorResource::collection($fornecedores);
    }

    public function store(Request $request)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar fornecedores.',
            ], 403);
        }

        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'nome_fantasia' => 'nullable|string|max:255',
            'endereco' => 'nullable|string',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'contato' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        $fornecedor = Fornecedor::create($validated);

        return new FornecedorResource($fornecedor);
    }

    public function show(Fornecedor $fornecedor)
    {
        return new FornecedorResource($fornecedor);
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar fornecedores.',
            ], 403);
        }

        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'nome_fantasia' => 'nullable|string|max:255',
            'endereco' => 'nullable|string',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'contato' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        $fornecedor->update($validated);

        return new FornecedorResource($fornecedor);
    }

    public function destroy(Fornecedor $fornecedor)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir fornecedores.',
            ], 403);
        }

        if ($fornecedor->orcamentos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um fornecedor que possui orçamentos vinculados.'
            ], 403);
        }

        $fornecedor->delete();

        return response()->json(null, 204);
    }
}




