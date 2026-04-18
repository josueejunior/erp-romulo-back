<?php

namespace App\Modules\Orcamento\Domain\Services;

use App\Modules\Orcamento\Domain\ValueObjects\ConfiguracaoExportacao;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\OrcamentoItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ExportacaoDomainService
{
    /**
     * Exportar orçamentos para Excel
     */
    public function exportarOrcamentos(
        int $empresaId,
        ConfiguracaoExportacao $configuracao,
        ?array $filtros = null
    ): string {
        $orcamentos = $this->obterOrcamentosComFiltros($empresaId, $filtros);

        if ($configuracao->getFormato() === 'excel') {
            return $this->exportarExcel($orcamentos, $configuracao);
        } elseif ($configuracao->getFormato() === 'pdf') {
            return $this->exportarPdf($orcamentos, $configuracao);
        } elseif ($configuracao->getFormato() === 'csv') {
            return $this->exportarCsv($orcamentos, $configuracao);
        }

        throw new \Exception('Formato de exportação não suportado');
    }

    /**
     * Exportar análise de preços
     */
    public function exportarAnalisePrecos(
        int $empresaId,
        ConfiguracaoExportacao $configuracao
    ): string {
        $dados = [];

        $items = OrcamentoItem::whereHas('orcamento', function ($query) use ($empresaId) {
            $query->where('empresa_id', $empresaId);
        })->get();

        foreach ($items as $item) {
            $dados[] = [
                'item_id' => $item->id,
                'descricao' => $item->processoItem?->descricao ?? 'N/A',
                'valor_minimo' => $item->valor_unitario,
                'valor_maximo' => $item->valor_unitario,
                'media' => $item->valor_unitario,
            ];
        }

        if ($configuracao->getFormato() === 'excel') {
            return $this->gerarArquivoExcel($dados, 'analise-precos', $configuracao);
        } elseif ($configuracao->getFormato() === 'pdf') {
            return $this->gerarArquivoPdf($dados, 'Análise de Preços', $configuracao);
        }

        return $this->gerarArquivoCsv($dados, 'analise-precos');
    }

    /**
     * Exportar relatório customizado
     */
    public function exportarRelatorio(
        int $empresaId,
        array $dados,
        string $titulo,
        ConfiguracaoExportacao $configuracao
    ): string {
        if ($configuracao->getFormato() === 'excel') {
            return $this->gerarArquivoExcel($dados, strtolower(str_replace(' ', '-', $titulo)), $configuracao);
        } elseif ($configuracao->getFormato() === 'pdf') {
            return $this->gerarArquivoPdf($dados, $titulo, $configuracao);
        }

        return $this->gerarArquivoCsv($dados, strtolower(str_replace(' ', '-', $titulo)));
    }

    // ====== MÉTODOS PRIVADOS ======

    private function obterOrcamentosComFiltros(int $empresaId, ?array $filtros): array
    {
        $query = Orcamento::where('empresa_id', $empresaId);

        if ($filtros) {
            if (isset($filtros['status'])) {
                $query->where('status', $filtros['status']);
            }
            if (isset($filtros['data_inicio'])) {
                $query->whereDate('created_at', '>=', $filtros['data_inicio']);
            }
            if (isset($filtros['data_fim'])) {
                $query->whereDate('created_at', '<=', $filtros['data_fim']);
            }
            if (isset($filtros['fornecedor_id'])) {
                $query->where('fornecedor_id', $filtros['fornecedor_id']);
            }
        }

        return $query->with(['fornecedor', 'processo', 'itens'])->get()->toArray();
    }

    private function exportarExcel(array $dados, ConfiguracaoExportacao $config): string
    {
        $arquivo = 'orcamentos-' . now()->format('YmdHis') . '.' . $config->getExtensao();
        if (class_exists('App\\Exports\\OrcamentosExport')) {
            Excel::store(
                new \App\Exports\OrcamentosExport($dados, $config->getCampos()),
                $arquivo,
                'local'
            );
            return $arquivo;
        }

        // Fallback simples para CSV se a classe de export não existir
        return $this->exportarCsv($dados, $config);
    }

    private function exportarPdf(array $dados, ConfiguracaoExportacao $config): string
    {
        $arquivo = 'orcamentos-' . now()->format('YmdHis') . '.pdf';
        if (class_exists('PDF')) {
            $pdf = PDF::loadView('exports.orcamentos', [
                'orcamentos' => $dados,
                'campos' => $config->getCampos()
            ]);
            Storage::put($arquivo, $pdf->output());
            return $arquivo;
        }

        // Fallback para CSV quando PDF não disponível
        return $this->exportarCsv($dados, $config);
    }

    private function exportarCsv(array $dados, ConfiguracaoExportacao $config): string
    {
        $arquivo = 'orcamentos-' . now()->format('YmdHis') . '.csv';
        $handle = fopen(storage_path('app/' . $arquivo), 'w');

        // Cabeçalho
        fputcsv($handle, $config->getCampos());

        // Dados
        foreach ($dados as $linha) {
            $valores = [];
            foreach ($config->getCampos() as $campo) {
                $valores[] = $linha[$campo] ?? '';
            }
            fputcsv($handle, $valores);
        }

        fclose($handle);

        return $arquivo;
    }

    private function gerarArquivoExcel(array $dados, string $nome, ConfiguracaoExportacao $config): string
    {
        $arquivo = $nome . '-' . now()->format('YmdHis') . '.' . $config->getExtensao();
        if (class_exists('App\\Exports\\GenericoExport')) {
            Excel::store(
                new \App\Exports\GenericoExport($dados, $config->getCampos()),
                $arquivo,
                'local'
            );
            return $arquivo;
        }

        // Fallback para CSV se não houver exportador configurado
        return $this->gerarArquivoCsv($dados, $nome);
    }

    private function gerarArquivoPdf(array $dados, string $titulo, ConfiguracaoExportacao $config): string
    {
        $arquivo = Str::slug($titulo) . '-' . now()->format('YmdHis') . '.pdf';
        if (class_exists('PDF')) {
            $pdf = PDF::loadView('exports.generico', [
                'titulo' => $titulo,
                'dados' => $dados,
                'campos' => $config->getCampos()
            ]);

            Storage::put($arquivo, $pdf->output());
            return $arquivo;
        }

        return $this->gerarArquivoCsv($dados, $titulo);
    }

    private function gerarArquivoCsv(array $dados, string $nome): string
    {
        $arquivo = $nome . '-' . now()->format('YmdHis') . '.csv';
        $handle = fopen(storage_path('app/' . $arquivo), 'w');

        if (!empty($dados)) {
            // Cabeçalho
            fputcsv($handle, array_keys($dados[0]));

            // Dados
            foreach ($dados as $linha) {
                fputcsv($handle, $linha);
            }
        }

        fclose($handle);

        return $arquivo;
    }
}
