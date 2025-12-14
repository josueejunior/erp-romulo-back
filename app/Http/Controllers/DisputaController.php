<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class DisputaController extends Controller
{
    public function edit(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar disputa de processos em execução.');
        }

        $processo->load('itens');

        return view('disputas.edit', compact('processo'));
    }

    public function update(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar disputa de processos em execução.');
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.valor_final_sessao' => 'nullable|numeric|min:0',
            'itens.*.classificacao' => 'nullable|integer|min:1',
        ]);

        foreach ($validated['itens'] as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if ($item && $item->processo_id === $processo->id) {
                $item->update([
                    'valor_final_sessao' => $itemData['valor_final_sessao'] ?? null,
                    'classificacao' => $itemData['classificacao'] ?? null,
                ]);
            }
        }

        // Sugerir mudança de status se a data da sessão já passou
        if ($processo->data_hora_sessao_publica->isPast() && $processo->status === 'participacao') {
            // Não muda automaticamente, apenas sugere
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Disputa registrada com sucesso!');
    }
}
