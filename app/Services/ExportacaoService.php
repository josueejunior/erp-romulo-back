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

        // Obter dados completos da empresa do tenant atual
        $tenant = null;
        $nomeEmpresa = 'Empresa não identificada';
        $cnpjEmpresa = '';
        $enderecoEmpresa = '';
        $cidadeEmpresa = '';
        $estadoEmpresa = '';
        $emailEmpresa = '';
        $telefoneEmpresa = '';
        $nomeFantasia = '';
        $bancoEmpresa = '';
        $agenciaEmpresa = '';
        $contaEmpresa = '';
        $representanteLegal = '';
        
        try {
            if (tenancy()->initialized) {
                $tenant = tenant();
                if ($tenant) {
                    $nomeEmpresa = $tenant->razao_social ?? 'Empresa não identificada';
                    $cnpjEmpresa = $tenant->cnpj ?? '';
                    $enderecoEmpresa = $tenant->endereco ?? '';
                    $cidadeEmpresa = $tenant->cidade ?? '';
                    $estadoEmpresa = $tenant->estado ?? '';
                    $emailEmpresa = $tenant->email ?? '';
                    $telefones = $tenant->telefones ?? [];
                    $telefoneEmpresa = is_array($telefones) && !empty($telefones) ? $telefones[0] : '';
                    $nomeFantasia = $tenant->nome_fantasia ?? $nomeEmpresa;
                    $bancoEmpresa = $tenant->banco ?? '';
                    $agenciaEmpresa = $tenant->agencia ?? '';
                    $contaEmpresa = $tenant->conta ?? '';
                    $representanteLegal = $tenant->representante_legal_nome ?? '';
                }
            }
        } catch (\Exception $e) {
            // Se houver erro, manter valores padrão
        }

        // Formatar endereço completo
        $enderecoCompleto = trim(implode(', ', array_filter([
            $enderecoEmpresa,
            $cidadeEmpresa,
            $estadoEmpresa
        ])));

        // Formatar data atual
        $dataAtual = Carbon::now();
        $dataFormatada = $dataAtual->format('d \d\e F \d\e Y');
        $meses = [
            'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
            'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
            'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
            'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
        ];
        foreach ($meses as $en => $pt) {
            $dataFormatada = str_replace($en, $pt, $dataFormatada);
        }

        $dados = [
            'processo' => $processo,
            'validade_proposta' => $validadeProposta,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'data_formatada' => $dataFormatada,
            'itens' => $processo->itens,
            'nome_empresa' => $nomeEmpresa,
            'nome_fantasia' => $nomeFantasia,
            'cnpj_empresa' => $cnpjEmpresa,
            'endereco_completo' => $enderecoCompleto,
            'cidade_empresa' => $cidadeEmpresa,
            'estado_empresa' => $estadoEmpresa,
            'email_empresa' => $emailEmpresa,
            'telefone_empresa' => $telefoneEmpresa,
            'banco_empresa' => $bancoEmpresa,
            'agencia_empresa' => $agenciaEmpresa,
            'conta_empresa' => $contaEmpresa,
            'representante_legal' => $representanteLegal,
            'tenant' => $tenant,
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

        // Obter nome da empresa do tenant atual
        $nomeEmpresa = 'Empresa não identificada';
        try {
            if (tenancy()->initialized) {
                $tenant = tenant();
                $nomeEmpresa = $tenant ? ($tenant->razao_social ?? 'Empresa não identificada') : 'Empresa não identificada';
            }
        } catch (\Exception $e) {
            // Se houver erro, manter valor padrão
        }

        $dados = [
            'processo' => $processo,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'itens' => $processo->itens,
            'nome_empresa' => $nomeEmpresa,
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

