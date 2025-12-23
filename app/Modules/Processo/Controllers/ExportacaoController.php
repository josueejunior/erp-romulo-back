<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Services\ExportacaoService;
use App\Modules\Processo\Models\Processo;
use Illuminate\Http\Request;

class ExportacaoController extends BaseApiController
{
    protected ExportacaoService $exportacaoService;

    public function __construct(ExportacaoService $exportacaoService)
    {
        $this->exportacaoService = $exportacaoService;
    }

    /**
     * Exporta proposta comercial (HTML ou PDF)
     */
    public function propostaComercial(Processo $processo, Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->exportacaoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->exportacaoService->validarProcessoPodeExportar($processo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível exportar para processos arquivados ou perdidos.' ? 403 : 404);
        }

        $html = $this->exportacaoService->gerarPropostaComercial($processo);

        // Se solicitado PDF, tentar converter
        if ($request->has('formato') && $request->formato === 'pdf') {
            $pdf = $this->exportacaoService->gerarPDF($html, "proposta_comercial_{$processo->id}.pdf");
            
            if ($pdf !== null) {
                return response($pdf, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.pdf"');
            } else {
                // Se dompdf não estiver instalado, retornar HTML com instruções
                return response($html)
                    ->header('Content-Type', 'text/html; charset=utf-8')
                    ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.html"')
                    ->header('X-PDF-Not-Available', 'true');
            }
        }

        // Retornar HTML por padrão
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.html"');
    }

    /**
     * Exporta catálogo/ficha técnica
     */
    public function catalogoFichaTecnica(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->exportacaoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->exportacaoService->validarProcessoPodeExportar($processo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível exportar para processos arquivados ou perdidos.' ? 403 : 404);
        }

        $html = $this->exportacaoService->gerarCatalogoFichaTecnica($processo);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="catalogo_ficha_tecnica_' . $processo->id . '.html"');
    }
}

