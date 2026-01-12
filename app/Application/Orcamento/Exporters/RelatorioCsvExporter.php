<?php

namespace App\Application\Orcamento\Exporters;

use App\Application\Orcamento\DTOs\RelatorioOrcamentosResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportador CSV para relatórios de orçamentos
 * 
 * ✅ DDD: Responsabilidade única - apenas formatação CSV
 */
class RelatorioCsvExporter implements RelatorioExporterInterface
{
    public function export(RelatorioOrcamentosResult $relatorio, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?? 'relatorio_orcamentos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($relatorio) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($file, [
                'ID',
                'Data',
                'Fornecedor',
                'Processo',
                'Valor Total',
                'Status',
                'Total de Itens',
            ], ';');

            // Dados
            foreach ($relatorio->dados as $item) {
                fputcsv($file, [
                    $item['id'] ?? '',
                    $item['data'] ?? '',
                    $item['fornecedor'] ?? '',
                    $item['processo'] ?? '',
                    number_format($item['valor_total'] ?? 0, 2, ',', '.'),
                    $item['status'] ?? '',
                    $item['total_itens'] ?? 0,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}





