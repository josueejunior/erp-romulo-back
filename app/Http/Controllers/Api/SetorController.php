<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\SetorResource;
use App\Models\Setor;
use App\Models\Orgao;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class SetorController extends BaseApiController
{
    public function index(Request $request)
    {
        if (!PermissionHelper::canView()) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }
        
        $empresa = $this->getEmpresaAtivaOrFail();
        $query = Setor::where('empresa_id', $empresa->id)->with('orgao');

        if ($request->orgao_id) {
            // Validar que o órgão pertence à empresa
            $orgao = Orgao::where('id', $request->orgao_id)
                ->where('empresa_id', $empresa->id)
                ->first();
            
            if (!$orgao) {
                return response()->json([
                    'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            $query->where('orgao_id', $request->orgao_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('nome', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $setors = $query->orderBy('nome')->paginate(15);

        return SetorResource::collection($setors);
    }

    public function store(Request $request)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar setores.',
            ], 403);
        }
        $empresa = $this->getEmpresaAtivaOrFail();

        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        // Validar que o órgão pertence à empresa
        $orgao = Orgao::where('id', $validated['orgao_id'])
            ->where('empresa_id', $empresa->id)
            ->first();
        
        if (!$orgao) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        // Validar que o nome do setor é único para o órgão
        $exists = Setor::where('orgao_id', $validated['orgao_id'])
            ->where('empresa_id', $empresa->id)
            ->where('nome', $validated['nome'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Já existe um setor com este nome para este órgão.',
                'errors' => [
                    'nome' => ['Já existe um setor com este nome para este órgão.']
                ]
            ], 422);
        }

        $validated['empresa_id'] = $empresa->id;
        $setor = Setor::create($validated);
        $setor->load('orgao');

        return new SetorResource($setor);
    }

    public function show(Setor $setor)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($setor->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Setor não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $setor->load('orgao');
        return new SetorResource($setor);
    }

    public function update(Request $request, Setor $setor)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar setores.',
            ], 403);
        }
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($setor->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Setor não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        // Validar que o nome do setor é único para o órgão (exceto o próprio setor)
        $exists = Setor::where('orgao_id', $setor->orgao_id)
            ->where('empresa_id', $empresa->id)
            ->where('nome', $validated['nome'])
            ->where('id', '!=', $setor->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Já existe um setor com este nome para este órgão.',
                'errors' => [
                    'nome' => ['Já existe um setor com este nome para este órgão.']
                ]
            ], 422);
        }

        $setor->update($validated);
        $setor->load('orgao');

        return new SetorResource($setor);
    }

    public function destroy(Setor $setor)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir setores.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($setor->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Setor não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($setor->processos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um setor que possui processos vinculados.',
            ], 403);
        }

        $setor->forceDelete();

        return response()->json(null, 204);
    }
}
