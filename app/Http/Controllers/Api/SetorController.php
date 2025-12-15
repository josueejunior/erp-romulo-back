<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SetorResource;
use App\Models\Setor;
use App\Models\Orgao;
use Illuminate\Http\Request;

class SetorController extends Controller
{
    public function index(Request $request)
    {
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
        $setor->delete();

        return response()->json(null, 204);
    }
}
