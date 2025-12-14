<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\Empresa;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $processosParticipacao = Processo::where('empresa_id', $empresa->id)
            ->where('status', 'participacao')
            ->count();

        $processosJulgamento = Processo::where('empresa_id', $empresa->id)
            ->where('status', 'julgamento_habilitacao')
            ->count();

        $processosExecucao = Processo::where('empresa_id', $empresa->id)
            ->where('status', 'execucao')
            ->count();

        $proximasDisputas = Processo::where('empresa_id', $empresa->id)
            ->whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->where('data_hora_sessao_publica', '>=', now())
            ->orderBy('data_hora_sessao_publica', 'asc')
            ->limit(5)
            ->get();

        // Documentos vencendo (próximos 30 dias)
        $documentosVencendo = $empresa->documentosHabilitacao()
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(30))
            ->orderBy('data_validade', 'asc')
            ->get();

        // Documentos vencidos
        $documentosVencidos = $empresa->documentosHabilitacao()
            ->whereNotNull('data_validade')
            ->where('data_validade', '<', now())
            ->orderBy('data_validade', 'desc')
            ->limit(5)
            ->get();

        // Contar documentos vencendo em 7 dias (urgente)
        $documentosUrgentes = $empresa->documentosHabilitacao()
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(7))
            ->count();

        return view('dashboard.index', compact(
            'processosParticipacao',
            'processosJulgamento',
            'processosExecucao',
            'proximasDisputas',
            'documentosVencendo',
            'documentosVencidos',
            'documentosUrgentes'
        ));
    }
}
