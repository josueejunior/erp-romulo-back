<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\OrgaoResource;
use App\Models\Orgao;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class OrgaoController extends BaseApiController
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Filtrar APENAS órgãos da empresa ativa (não incluir NULL)
        $query = Orgao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->with('setors');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('razao_social', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%");
            });
        }

        $orgaos = $query->orderBy('razao_social')->paginate(15);

        return OrgaoResource::collection($orgaos);
    }

    public function store(Request $request)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();

        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'telefones' => 'nullable|array',
            'telefones.*' => 'string|max:20',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $validated['empresa_id'] = $empresa->id;
        $orgao = Orgao::create($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function show(Orgao $orgao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $orgao->load('setors');
        return new OrgaoResource($orgao);
    }

    public function update(Request $request, Orgao $orgao)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'telefones' => 'nullable|array',
            'telefones.*' => 'string|max:20',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $orgao->update($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function destroy(Orgao $orgao)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($orgao->processos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um órgão que possui processos vinculados.'
            ], 403);
        }

        $orgao->forceDelete();

        return response()->json(null, 204);
    }
}




