<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\JulgamentoService;
use Illuminate\Http\Request;

class JulgamentoController extends Controller
{
    protected JulgamentoService $julgamentoService;

    public function __construct(JulgamentoService $julgamentoService)
    {
        $this->julgamentoService = $julgamentoService;
    }

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

    public function update(Request $request, Processo $processo)
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
