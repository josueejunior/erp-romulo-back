<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class JulgamentoController extends Controller
{
    public function edit(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar julgamento de processos em execução.');
        }

        $processo->load('itens');

        return view('julgamentos.edit', compact('processo'));
    }

    public function update(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        if ($processo->empresa_id !== $empresa->id) {
            abort(403);
        }

        if ($processo->isEmExecucao()) {
            return redirect()->route('processos.show', $processo)
                ->with('error', 'Não é possível editar julgamento de processos em execução.');
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.status_item' => 'required|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'itens.*.valor_negociado' => 'nullable|numeric|min:0',
            'itens.*.chance_arremate' => 'nullable|in:baixa,media,alta',
            'itens.*.chance_percentual' => 'nullable|integer|min:0|max:100',
            'itens.*.lembretes' => 'nullable|string',
        ]);

        $todosDesclassificadosOuInabilitados = true;
        $temAceito = false;

        foreach ($validated['itens'] as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if ($item && $item->processo_id === $processo->id) {
                $item->update([
                    'status_item' => $itemData['status_item'],
                    'valor_negociado' => $itemData['valor_negociado'] ?? null,
                    'chance_arremate' => $itemData['chance_arremate'] ?? null,
                    'chance_percentual' => $itemData['chance_percentual'] ?? null,
                    'lembretes' => $itemData['lembretes'] ?? null,
                ]);

                if (in_array($itemData['status_item'], ['aceito', 'aceito_habilitado'])) {
                    $temAceito = true;
                    $todosDesclassificadosOuInabilitados = false;
                } elseif (!in_array($itemData['status_item'], ['desclassificado', 'inabilitado'])) {
                    $todosDesclassificadosOuInabilitados = false;
                }
            }
        }

        // Atualizar status do processo se necessário
        if ($todosDesclassificadosOuInabilitados && $processo->status === 'julgamento_habilitacao') {
            // Sistema sugere PERDIDO, mas não muda automaticamente
        } elseif ($temAceito && $processo->status === 'participacao') {
            // Se houver item aceito e ainda está em participação, sugerir mudança para julgamento
            if ($processo->data_hora_sessao_publica->isPast()) {
                // Sugerir mudança manual
            }
        }

        return redirect()->route('processos.show', $processo)
            ->with('success', 'Julgamento atualizado com sucesso!');
    }
}
