<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\JulgamentoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JulgamentoController extends BaseApiController
{
    protected JulgamentoService $julgamentoService;

    public function __construct(JulgamentoService $julgamentoService)
    {
        $this->julgamentoService = $julgamentoService;
    }

    /**
     * API: Buscar dados do julgamento de um processo
     */
    public function show(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->julgamentoService->validarProcessoEmpresa($processo, $empresa->id);
            
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
     * API: Atualizar julgamento de um processo
     */
    public function update(Request $request, Processo $processo): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->julgamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->julgamentoService->registrarJulgamento($processo, $request->input('itens', []));

            $processo->refresh();
            $processo->load('itens');

            return response()->json([
                'message' => 'Julgamento atualizado com sucesso',
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
     * Web: Editar julgamento (mantido para compatibilidade)
     */
    public function edit(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->julgamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->julgamentoService->validarProcessoPodeEditar($processo);
        } catch (\Exception $e) {
            return redirect()->route('processos.show', $processo)
                ->with('error', $e->getMessage());
        }

        $processo->load('itens');

        return view('julgamentos.edit', compact('processo'));
    }

    /**
     * Web: Atualizar julgamento (mantido para compatibilidade)
     */
    public function updateWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            $this->julgamentoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->julgamentoService->registrarJulgamento($processo, $request->input('itens', []));

            return redirect()->route('processos.show', $processo)
                ->with('success', 'Julgamento atualizado com sucesso!');
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
