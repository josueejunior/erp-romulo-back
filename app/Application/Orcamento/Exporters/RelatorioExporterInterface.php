<?php

namespace App\Application\Orcamento\Exporters;

use App\Application\Orcamento\DTOs\RelatorioOrcamentosResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Interface para exportação de relatórios
 * 
 * ✅ DDD: Separação de responsabilidades
 * Controller não formata dados, apenas decide qual exportador usar
 */
interface RelatorioExporterInterface
{
    /**
     * Exporta relatório no formato específico
     */
    public function export(RelatorioOrcamentosResult $relatorio, ?string $filename = null): StreamedResponse;
}







