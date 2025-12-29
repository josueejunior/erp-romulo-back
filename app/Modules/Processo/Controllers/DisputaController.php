<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\DisputaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DisputaController extends BaseApiController
{
    protected DisputaService $disputaService;

    public function __construct(DisputaService $disputaService)
    {
        $this->disputaService = $disputaService;
    }

    /**
     * API: Buscar dados da disputa de um processo
     */
    public function show(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->disputaService->validarProcessoEmpresa($processo, $empresa->id);
            
            $processo->load('itens');
            
            return response()->json([
                'data' => [
                    'processo' => $processo,
                    'itens' => $processo->itens,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Atualizar disputa de um processo
     */
    public function update(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->disputaService->validarProcessoEmpresa($processo, $empresa->id);
            $this->disputaService->registrarResultados($processo, $request->input('itens', []));

            $processo->refresh();
            $processo->load('itens');

            return response()->json([
                'message' => 'Disputa registrada com sucesso',
                'data' => [
                    'processo' => $processo,
                    'itens' => $processo->itens,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Web: Editar disputa (mantido para compatibilidade)
     */
    public function edit(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->disputaService->validarProcessoEmpresa($processo, $empresa->id);
            $this->disputaService->validarProcessoPodeEditar($processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        $processo->load('itens');

        return view('disputas.edit', compact('processo'));
    }

    /**
     * Web: Atualizar disputa (mantido para compatibilidade)
     */
    public function updateWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->disputaService->validarProcessoEmpresa($processo, $empresa->id);
            $this->disputaService->registrarResultados($processo, $request->input('itens', []));

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Disputa registrada com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }
    }
}
