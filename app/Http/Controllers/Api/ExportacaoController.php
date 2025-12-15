<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportacaoService;
use App\Models\Processo;
use Illuminate\Http\Request;

class ExportacaoController extends Controller
{
    protected ExportacaoService $exportacaoService;

    public function __construct(ExportacaoService $exportacaoService)
    {
        $this->exportacaoService = $exportacaoService;
    }

    /**
     * Exporta proposta comercial
     */
    public function propostaComercial(Processo $processo)
    {
        if ($processo->status !== 'julgamento_habilitacao') {
            return response()->json([
                'message' => 'Apenas processos em julgamento podem ter proposta comercial exportada.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarPropostaComercial($processo);

        // Retornar HTML (você pode converter para PDF usando dompdf, snappy, etc)
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Exporta catálogo/ficha técnica
     */
    public function catalogoFichaTecnica(Processo $processo)
    {
        if ($processo->status !== 'julgamento_habilitacao') {
            return response()->json([
                'message' => 'Apenas processos em julgamento podem ter catálogo exportado.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarCatalogoFichaTecnica($processo);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}

