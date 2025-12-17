<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\ExportacaoService;
use App\Models\Processo;
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
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Permitir exportação em qualquer status, exceto arquivado/perdido
        // Conforme especificação: pode ser gerada na fase de participação e julgamento
        if (in_array($processo->status, ['arquivado', 'perdido'])) {
            return response()->json([
                'message' => 'Não é possível exportar proposta comercial para processos arquivados ou perdidos.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarPropostaComercial($processo);

        // Se solicitado PDF, tentar converter (requer dompdf instalado)
        if ($request->has('formato') && $request->formato === 'pdf') {
            try {
                // Tentar usar dompdf se disponível
                if (class_exists(\Dompdf\Dompdf::class)) {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    return response($dompdf->output(), 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.pdf"');
                } else {
                    // Se dompdf não estiver instalado, retornar HTML com instruções
                    return response($html)
                        ->header('Content-Type', 'text/html; charset=utf-8')
                        ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.html"')
                        ->header('X-PDF-Not-Available', 'true');
                }
            } catch (\Exception $e) {
                // Em caso de erro, retornar HTML
                return response($html)
                    ->header('Content-Type', 'text/html; charset=utf-8')
                    ->header('Content-Disposition', 'inline; filename="proposta_comercial_' . $processo->id . '.html"');
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
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Permitir exportação em qualquer status, exceto arquivado/perdido
        // Conforme especificação: pode ser gerada na fase de participação e julgamento
        if (in_array($processo->status, ['arquivado', 'perdido'])) {
            return response()->json([
                'message' => 'Não é possível exportar catálogo/ficha técnica para processos arquivados ou perdidos.'
            ], 403);
        }

        $html = $this->exportacaoService->gerarCatalogoFichaTecnica($processo);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="catalogo_ficha_tecnica_' . $processo->id . '.html"');
    }
}

