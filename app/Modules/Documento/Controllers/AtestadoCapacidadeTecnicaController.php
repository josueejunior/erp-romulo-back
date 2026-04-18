<?php

namespace App\Modules\Documento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Documento\Models\AtestadoCapacidadeTecnica;
use App\Http\Middleware\EnsureEmpresaAtivaContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AtestadoCapacidadeTecnicaController extends BaseApiController
{
    use HasAuthContext;

    public function __construct()
    {
        $this->middleware(EnsureEmpresaAtivaContext::class);
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $query = AtestadoCapacidadeTecnica::where('empresa_id', $empresa->id);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('contratante', 'ilike', "%{$search}%")
                      ->orWhere('objeto', 'ilike', "%{$search}%")
                      ->orWhere('cnpj_contratante', 'ilike', "%{$search}%");
                });
            }

            $atestados = $query->orderByDesc('criado_em')->paginate(15);

            return response()->json($atestados);
        } catch (\Exception $e) {
            Log::error('AtestadoController::list', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function get(Request $request, int|string $id = null): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $id = $id ?? $request->route('atestado');
            $atestado = AtestadoCapacidadeTecnica::where('empresa_id', $empresa->id)->findOrFail($id);
            return response()->json(['data' => $atestado]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Atestado não encontrado.'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();

            $validated = $request->validate([
                'contratante'      => 'required|string|max:255',
                'cnpj_contratante' => 'nullable|string|max:20',
                'objeto'           => 'required|string',
                'valor_contrato'   => 'nullable|numeric|min:0',
                'data_inicio'      => 'nullable|date',
                'data_fim'         => 'nullable|date',
                'arquivo'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'observacoes'      => 'nullable|string',
            ]);

            $arquivoPath = null;
            if ($request->hasFile('arquivo')) {
                $arquivoPath = $request->file('arquivo')->store('atestados-capacidade-tecnica');
                $validated['arquivo'] = basename($arquivoPath);
            }

            $atestado = AtestadoCapacidadeTecnica::create(array_merge($validated, [
                'empresa_id' => $empresa->id,
            ]));

            return response()->json([
                'message' => 'Atestado cadastrado com sucesso!',
                'data'    => $atestado,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Dados inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AtestadoController::store', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, int|string $id = null): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $id = $id ?? $request->route('atestado');
            $atestado = AtestadoCapacidadeTecnica::where('empresa_id', $empresa->id)->findOrFail($id);

            $validated = $request->validate([
                'contratante'      => 'sometimes|required|string|max:255',
                'cnpj_contratante' => 'nullable|string|max:20',
                'objeto'           => 'sometimes|required|string',
                'valor_contrato'   => 'nullable|numeric|min:0',
                'data_inicio'      => 'nullable|date',
                'data_fim'         => 'nullable|date',
                'arquivo'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'observacoes'      => 'nullable|string',
            ]);

            if ($request->hasFile('arquivo')) {
                if ($atestado->arquivo) {
                    Storage::delete('atestados-capacidade-tecnica/' . $atestado->arquivo);
                }
                $path = $request->file('arquivo')->store('atestados-capacidade-tecnica');
                $validated['arquivo'] = basename($path);
            }

            $atestado->update($validated);

            return response()->json([
                'message' => 'Atestado atualizado com sucesso!',
                'data'    => $atestado->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Dados inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AtestadoController::update', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, int|string $id = null): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $id = $id ?? $request->route('atestado');
            $atestado = AtestadoCapacidadeTecnica::where('empresa_id', $empresa->id)->findOrFail($id);

            if ($atestado->arquivo) {
                Storage::delete('atestados-capacidade-tecnica/' . $atestado->arquivo);
            }

            $atestado->delete();

            return response()->json(['message' => 'Atestado excluído com sucesso!']);
        } catch (\Exception $e) {
            Log::error('AtestadoController::destroy', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function download(Request $request, int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $atestado = AtestadoCapacidadeTecnica::where('empresa_id', $empresa->id)->findOrFail($id);

            if (!$atestado->arquivo) {
                return response()->json(['message' => 'Nenhum arquivo disponível.'], 404);
            }

            $path = 'atestados-capacidade-tecnica/' . $atestado->arquivo;
            if (!Storage::exists($path)) {
                return response()->json(['message' => 'Arquivo não encontrado.'], 404);
            }

            return Storage::download($path);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao baixar arquivo.'], 500);
        }
    }
}
