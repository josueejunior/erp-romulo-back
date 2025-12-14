<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use Illuminate\Http\Request;

class CalendarioDisputasController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $query = Processo::where('empresa_id', $empresa->id)
            ->whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->with(['orgao', 'setor', 'itens.formacoesPreco']);

        if ($request->data_inicio) {
            $query->where('data_hora_sessao_publica', '>=', $request->data_inicio);
        }

        if ($request->data_fim) {
            $query->where('data_hora_sessao_publica', '<=', $request->data_fim);
        }

        $processos = $query->orderBy('data_hora_sessao_publica', 'asc')->get();

        return view('calendario.index', compact('processos'));
    }
}
