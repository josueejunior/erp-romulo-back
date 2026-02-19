<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoNota;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Notas rápidas do processo (anotações de execução)
 *
 * Exemplos de uso:
 * - "Fornecedor informou entrega em 20/02"
 * - "Enviado e-mail cobrando NF"
 */
class ProcessoNotaController extends BaseApiController
{
    use HasAuthContext;

    /**
     * Lista notas de um processo.
     * GET /processos/{processo}/notas
     */
    public function index(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Processo não pertence à empresa ativa'], 403);
        }

        $notas = ProcessoNota::query()
            ->where('empresa_id', $empresa->id)
            ->where('processo_id', $processo->id)
            ->orderByDesc('data_referencia')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $notas,
            'total' => $notas->count(),
        ]);
    }

    /**
     * Cria uma nova nota para o processo.
     * POST /processos/{processo}/notas
     */
    public function store(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Processo não pertence à empresa ativa'], 403);
        }

        $validated = $request->validate([
            'texto' => 'required|string',
            'titulo' => 'nullable|string|max:255',
            'data_referencia' => 'nullable|date',
        ]);

        $dataReferencia = $validated['data_referencia'] ?? now()->toDateString();

        $nota = ProcessoNota::create([
            'empresa_id' => $empresa->id,
            'processo_id' => $processo->id,
            'usuario_id' => auth()->id(),
            'titulo' => $validated['titulo'] ?? null,
            'texto' => $validated['texto'],
            'data_referencia' => $dataReferencia,
        ]);

        return response()->json([
            'message' => 'Nota criada com sucesso.',
            'data' => $nota,
        ], 201);
    }

    /**
     * Remove uma nota do processo.
     * DELETE /processos/{processo}/notas/{nota}
     */
    public function destroy(Request $request, Processo $processo, ProcessoNota $nota): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id || $nota->empresa_id !== $empresa->id || $nota->processo_id !== $processo->id) {
            return response()->json(['message' => 'Nota não pertence a este processo/empresa'], 403);
        }

        $nota->delete();

        return response()->json([
            'message' => 'Nota removida com sucesso.',
        ]);
    }
}

