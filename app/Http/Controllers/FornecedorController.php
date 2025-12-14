<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $query = Fornecedor::where('empresa_id', $empresa->id);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('razao_social', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%")
                  ->orWhere('nome_fantasia', 'like', "%{$request->search}%");
            });
        }

        $fornecedores = $query->orderBy('razao_social', 'asc')->paginate(15);

        return view('fornecedores.index', compact('fornecedores'));
    }

    public function create()
    {
        return view('fornecedores.create');
    }

    public function store(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

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

        $validated['empresa_id'] = $empresa->id;

        Fornecedor::create($validated);

        return redirect()->route('fornecedores.index')
            ->with('success', 'Fornecedor cadastrado com sucesso!');
    }

    public function show(Fornecedor $fornecedor)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($fornecedor->empresa_id !== $empresa->id) {
            abort(403);
        }

        $fornecedor->load('transportadoras', 'orcamentos');

        return view('fornecedores.show', compact('fornecedor'));
    }

    public function edit(Fornecedor $fornecedor)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($fornecedor->empresa_id !== $empresa->id) {
            abort(403);
        }

        return view('fornecedores.edit', compact('fornecedor'));
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($fornecedor->empresa_id !== $empresa->id) {
            abort(403);
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

        return redirect()->route('fornecedores.show', $fornecedor)
            ->with('success', 'Fornecedor atualizado com sucesso!');
    }

    public function destroy(Fornecedor $fornecedor)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($fornecedor->empresa_id !== $empresa->id) {
            abort(403);
        }

        $fornecedor->delete();

        return redirect()->route('fornecedores.index')
            ->with('success', 'Fornecedor excluído com sucesso!');
    }
}
