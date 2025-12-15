<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoHabilitacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoHabilitacaoController extends Controller
{
    public function index(Request $request)
    {
        $query = DocumentoHabilitacao::query();

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('tipo', 'like', "%{$request->search}%")
                  ->orWhere('numero', 'like', "%{$request->search}%");
            });
        }

        if ($request->vencendo) {
            $query->whereNotNull('data_validade')
                  ->where('data_validade', '>=', now())
                  ->where('data_validade', '<=', now()->addDays(30));
        }

        $documentos = $query->orderBy('data_validade', 'asc')->paginate(15);

        return response()->json($documentos);
    }

    public function store(Request $request)
    {
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
            $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $documento = DocumentoHabilitacao::create($validated);

        return response()->json($documento, 201);
    }

    public function show(DocumentoHabilitacao $documentoHabilitacao)
    {
        return response()->json($documentoHabilitacao);
    }

    public function update(Request $request, DocumentoHabilitacao $documentoHabilitacao)
    {
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
            if ($documentoHabilitacao->arquivo) {
                Storage::disk('public')->delete('documentos-habilitacao/' . $documentoHabilitacao->arquivo);
            }
            $arquivo = $request->file('arquivo');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $validated['arquivo'] = $nomeArquivo;
        }

        $documentoHabilitacao->update($validated);

        return response()->json($documentoHabilitacao);
    }

    public function destroy(DocumentoHabilitacao $documentoHabilitacao)
    {
        if ($documentoHabilitacao->processoDocumentos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um documento que está vinculado a processos.'
            ], 403);
        }

        if ($documentoHabilitacao->arquivo) {
            Storage::disk('public')->delete('documentos-habilitacao/' . $documentoHabilitacao->arquivo);
        }

        $documentoHabilitacao->delete();

        return response()->json(null, 204);
    }
}




