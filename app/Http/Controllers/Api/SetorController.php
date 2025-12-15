<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SetorResource;
use App\Models\Setor;
use App\Models\Orgao;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class SetorController extends Controller
{
    public function index(Request $request)
    {
        if (!PermissionHelper::canView()) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }
        $query = Setor::with('orgao');

        if ($request->orgao_id) {
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
        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        $setor = Setor::create($validated);
        $setor->load('orgao');

        return new SetorResource($setor);
    }

    public function show(Setor $setor)
    {
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

        if ($setor->processos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um setor que possui processos vinculados.',
            ], 403);
        }

        $setor->delete();

        return response()->json(null, 204);
    }
}
