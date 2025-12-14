<?php

namespace App\Http\Controllers;

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
                  ->orWhere('uasg', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%");
            });
        }

        $orgaos = $query->orderBy('razao_social', 'asc')->paginate(15);

        return view('orgaos.index', compact('orgaos'));
    }

    public function create()
    {
        return view('orgaos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'endereco' => 'nullable|string',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        Orgao::create($validated);

        return redirect()->route('orgaos.index')
            ->with('success', 'Órgão cadastrado com sucesso!');
    }

    public function show(Orgao $orgao)
    {
        $orgao->load('setors', 'processos');

        return view('orgaos.show', compact('orgao'));
    }

    public function edit(Orgao $orgao)
    {
        return view('orgaos.edit', compact('orgao'));
    }

    public function update(Request $request, Orgao $orgao)
    {
        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'endereco' => 'nullable|string',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        $orgao->update($validated);

        return redirect()->route('orgaos.show', $orgao)
            ->with('success', 'Órgão atualizado com sucesso!');
    }

    public function destroy(Orgao $orgao)
    {
        // Verificar se tem processos vinculados
        if ($orgao->processos()->count() > 0) {
            return redirect()->route('orgaos.index')
                ->with('error', 'Não é possível excluir um órgão que possui processos vinculados.');
        }

        $orgao->delete();

        return redirect()->route('orgaos.index')
            ->with('success', 'Órgão excluído com sucesso!');
    }
}
