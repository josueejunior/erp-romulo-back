<?php

namespace App\Http\Controllers;

use App\Models\DocumentoHabilitacao;
use Illuminate\Http\Request;

class DocumentoHabilitacaoController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $query = DocumentoHabilitacao::where('empresa_id', $empresa->id);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('tipo', 'like', "%{$request->search}%")
                  ->orWhere('numero', 'like', "%{$request->search}%")
                  ->orWhere('identificacao', 'like', "%{$request->search}%");
            });
        }

        if ($request->vencendo) {
            $query->where('data_validade', '>=', now())
                  ->where('data_validade', '<=', now()->addDays(30));
        }

        if ($request->vencido) {
            $query->where('data_validade', '<', now());
        }

        $documentos = $query->orderBy('data_validade', 'asc')->paginate(15);

        return view('documentos-habilitacao.index', compact('documentos'));
    }

    public function create()
    {
        return view('documentos-habilitacao.create');
    }

    public function store(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $validated = $request->validate([
            'tipo' => 'required|string|max:255',
            'numero' => 'nullable|string|max:255',
            'identificacao' => 'nullable|string|max:255',
            'data_emissao' => 'nullable|date',
            'data_validade' => 'nullable|date',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'observacoes' => 'nullable|string',
        ]);

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('documentos', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $validated['empresa_id'] = $empresa->id;

        DocumentoHabilitacao::create($validated);

        return redirect()->route('documentos-habilitacao.index')
            ->with('success', 'Documento cadastrado com sucesso!');
    }

    public function show(DocumentoHabilitacao $documentoHabilitacao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            abort(403);
        }

        return view('documentos-habilitacao.show', compact('documentoHabilitacao'));
    }

    public function edit(DocumentoHabilitacao $documentoHabilitacao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            abort(403);
        }

        return view('documentos-habilitacao.edit', compact('documentoHabilitacao'));
    }

    public function update(Request $request, DocumentoHabilitacao $documentoHabilitacao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            abort(403);
        }

        $validated = $request->validate([
            'tipo' => 'required|string|max:255',
            'numero' => 'nullable|string|max:255',
            'identificacao' => 'nullable|string|max:255',
            'data_emissao' => 'nullable|date',
            'data_validade' => 'nullable|date',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'observacoes' => 'nullable|string',
        ]);

        if ($request->hasFile('arquivo')) {
            // Remove arquivo antigo se existir
            if ($documentoHabilitacao->arquivo) {
                \Storage::disk('public')->delete('documentos/' . $documentoHabilitacao->arquivo);
            }

            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('documentos', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $documentoHabilitacao->update($validated);

        return redirect()->route('documentos-habilitacao.show', $documentoHabilitacao)
            ->with('success', 'Documento atualizado com sucesso!');
    }

    public function destroy(DocumentoHabilitacao $documentoHabilitacao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            abort(403);
        }

        // Remove arquivo se existir
        if ($documentoHabilitacao->arquivo) {
            \Storage::disk('public')->delete('documentos/' . $documentoHabilitacao->arquivo);
        }

        $documentoHabilitacao->delete();

        return redirect()->route('documentos-habilitacao.index')
            ->with('success', 'Documento excluído com sucesso!');
    }
}
