<?php

namespace App\Http\Controllers;

use App\Models\Setor;
use App\Models\Orgao;
use Illuminate\Http\Request;

class SetorController extends Controller
{
    public function create(Request $request)
    {
        $orgao = Orgao::findOrFail($request->orgao_id);
        return view('setors.create', compact('orgao'));
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

        Setor::create($validated);

        return redirect()->route('orgaos.show', $validated['orgao_id'])
            ->with('success', 'Setor cadastrado com sucesso!');
    }

    public function edit(Setor $setor)
    {
        $setor->load('orgao');
        return view('setors.edit', compact('setor'));
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

        return redirect()->route('orgaos.show', $setor->orgao_id)
            ->with('success', 'Setor atualizado com sucesso!');
    }

    public function destroy(Setor $setor)
    {
        // Verificar se tem processos vinculados
        if ($setor->processos()->count() > 0) {
            return redirect()->route('orgaos.show', $setor->orgao_id)
                ->with('error', 'Não é possível excluir um setor que possui processos vinculados.');
        }

        $orgaoId = $setor->orgao_id;
        $setor->delete();

        return redirect()->route('orgaos.show', $orgaoId)
            ->with('success', 'Setor excluído com sucesso!');
    }
}
