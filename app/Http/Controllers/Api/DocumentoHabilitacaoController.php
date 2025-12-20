<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\DocumentoHabilitacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Helpers\PermissionHelper;

class DocumentoHabilitacaoController extends BaseApiController
{

    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Filtrar APENAS documentos da empresa ativa (não incluir NULL)
        $query = DocumentoHabilitacao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id');

        // Filtrar apenas documentos não deletados (soft deletes são automaticamente excluídos)

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

        // Se não for paginação, retornar todos
        if ($request->boolean('todos')) {
            $documentos = $query->orderBy('tipo', 'asc')->get();
            return response()->json($documentos);
        }

        $documentos = $query->orderBy('data_validade', 'asc')->paginate(15);

        return response()->json($documentos);
    }

    public function store(Request $request)
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar documentos de habilitação.',
            ], 403);
        }

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

        $validated['empresa_id'] = $empresa->id;

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
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Documento não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        return response()->json($documentoHabilitacao);
    }

    public function update(Request $request, DocumentoHabilitacao $documentoHabilitacao)
    {
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar documentos de habilitação.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Documento não encontrado ou não pertence à empresa ativa.'
            ], 404);
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
        if (!PermissionHelper::canManageDocuments()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir documentos de habilitação.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($documentoHabilitacao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Documento não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($documentoHabilitacao->processoDocumentos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um documento que está vinculado a processos.'
            ], 403);
        }

        if ($documentoHabilitacao->arquivo) {
            Storage::disk('public')->delete('documentos-habilitacao/' . $documentoHabilitacao->arquivo);
        }

        // Forçar exclusão permanente (não soft delete)
        $documentoHabilitacao->forceDelete();

        return response()->json(null, 204);
    }
}






