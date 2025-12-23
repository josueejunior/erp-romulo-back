<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\DisputaService;
use Illuminate\Http\Request;

class DisputaController extends Controller
{
    protected DisputaService $disputaService;

    public function __construct(DisputaService $disputaService)
    {
        $this->disputaService = $disputaService;
    }

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

    public function update(Request $request, Processo $processo)
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
