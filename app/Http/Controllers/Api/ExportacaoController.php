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
        // Permitir exportação em participação e julgamento
        if (!in_array($processo->status, ['participacao', 'julgamento_habilitacao'])) {
            return response()->json([
                'message' => 'Apenas processos em participação ou julgamento podem ter proposta comercial exportada.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarPropostaComercial($processo);

        // Retornar HTML (pode ser convertido para PDF no frontend ou usando dompdf)
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.html"');
    }

    /**
     * Exporta catálogo/ficha técnica
     */
    public function catalogoFichaTecnica(Processo $processo)
    {
        // Permitir exportação em participação e julgamento
        if (!in_array($processo->status, ['participacao', 'julgamento_habilitacao'])) {
            return response()->json([
                'message' => 'Apenas processos em participação ou julgamento podem ter catálogo exportado.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarCatalogoFichaTecnica($processo);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="catalogo_ficha_tecnica_' . $processo->id . '.html"');
    }
}

