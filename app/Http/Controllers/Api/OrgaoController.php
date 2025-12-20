<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrgaoResource;
use App\Models\Orgao;
use Illuminate\Http\Request;

class OrgaoController extends Controller
{
    public function index(Request $request)
    {
        $query = Orgao::with('setors');

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
        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $orgao = Orgao::create($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function show(Orgao $orgao)
    {
        $orgao->load('setors');
        return new OrgaoResource($orgao);
    }

    public function update(Request $request, Orgao $orgao)
    {
        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $orgao->update($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function destroy(Orgao $orgao)
    {
        if ($orgao->processos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um órgão que possui processos vinculados.'
            ], 403);
        }

        $orgao->delete();

        return response()->json(null, 204);
    }
}






