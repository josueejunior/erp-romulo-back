<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

class ExportacaoService
{
    /**
     * Gera proposta comercial em PDF
     */
    public function gerarPropostaComercial(Processo $processo): string
    {
        $processo->load([
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->orderBy('numero_item');
            },
            'itens.orcamentos' => function ($query) {
                $query->with(['fornecedor', 'formacaoPreco']);
            }
        ]);

        // Calcular validade proporcional
        $validadeProposta = $this->calcularValidadeProposta($processo);

        $dados = [
            'processo' => $processo,
            'validade_proposta' => $validadeProposta,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'itens' => $processo->itens,
        ];

        // Retornar HTML para conversão em PDF
        return View::make('exports.proposta_comercial', $dados)->render();
    }

    /**
     * Gera catálogo/ficha técnica em PDF
     */
    public function gerarCatalogoFichaTecnica(Processo $processo): string
    {
        $processo->load([
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->orderBy('numero_item');
            },
            'itens.orcamentos' => function ($query) {
                $query->with(['fornecedor', 'formacaoPreco']);
            }
        ]);

        $dados = [
            'processo' => $processo,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'itens' => $processo->itens,
        ];

        return View::make('exports.catalogo_ficha_tecnica', $dados)->render();
    }

    /**
     * Calcula validade da proposta proporcional à data de elaboração
     */
    protected function calcularValidadeProposta(Processo $processo): string
    {
        if (!$processo->validade_proposta_inicio || !$processo->validade_proposta_fim) {
            return $processo->validade_proposta ?? 'Não especificada';
        }

        $inicio = Carbon::parse($processo->validade_proposta_inicio);
        $fim = Carbon::parse($processo->validade_proposta_fim);
        $hoje = Carbon::now();

        // Se hoje está dentro do período
        if ($hoje->between($inicio, $fim)) {
            $diasRestantes = $hoje->diffInDays($fim);
            return "Válida até {$fim->format('d/m/Y')} ({$diasRestantes} dias restantes)";
        }

        // Se já passou
        if ($hoje->isAfter($fim)) {
            return "Vencida em {$fim->format('d/m/Y')}";
        }

        // Se ainda não começou
        return "Válida de {$inicio->format('d/m/Y')} até {$fim->format('d/m/Y')}";
    }
}

