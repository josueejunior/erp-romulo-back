<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;

class ProcessoValidationService
{
    /**
     * Valida se o processo pode avançar para uma nova fase
     */
    public function podeAvançarFase(Processo $processo, string $novaFase): array
    {
        $erros = [];
        $avisos = [];

        switch ($novaFase) {
            case 'julgamento_habilitacao':
                // Validar que a sessão pública já aconteceu ou está próxima
                if ($processo->data_hora_sessao_publica && 
                    $processo->data_hora_sessao_publica->isFuture()) {
                    $avisos[] = 'A sessão pública ainda não aconteceu. Deseja continuar mesmo assim?';
                }
                break;

            case 'execucao':
            case 'vencido':
                // Validar que há itens vencidos
                $itensVencidos = $processo->itens()
                    ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
                    ->orWhere('situacao_final', 'vencido')
                    ->count();
                
                if ($itensVencidos === 0) {
                    $erros[] = 'Não há itens vencidos para iniciar a execução.';
                }

                // Validar que há orçamentos escolhidos (opcional, mas recomendado)
                $itensComOrcamento = $processo->itens()
                    ->whereHas('orcamentoItens', function ($q) {
                        $q->where('fornecedor_escolhido', true);
                    })
                    ->count();
                
                if ($itensComOrcamento === 0 && $processo->itens()->count() > 0) {
                    $avisos[] = 'Nenhum orçamento foi escolhido. É recomendado escolher orçamentos antes de iniciar a execução.';
                }
                break;

            case 'pagamento':
                // Validar que há documentos de execução (contrato, AF ou empenho)
                $temDocumentos = $processo->contratos()->exists() ||
                                 $processo->autorizacoesFornecimento()->exists() ||
                                 $processo->empenhos()->exists();
                
                if (!$temDocumentos) {
                    $erros[] = 'Não há documentos de execução (Contrato, AF ou Empenho) vinculados ao processo.';
                }
                break;

            case 'encerramento':
                // Validar que há data de recebimento de pagamento
                if (!$processo->data_recebimento_pagamento) {
                    $erros[] = 'É necessário informar a data de recebimento do pagamento antes de encerrar o processo.';
                }

                // Validar que há notas fiscais de saída (opcional, mas recomendado)
                $temNotasSaida = $processo->notasFiscais()
                    ->where('tipo', 'saida')
                    ->exists();
                
                if (!$temNotasSaida) {
                    $avisos[] = 'Não há notas fiscais de saída registradas. É recomendado registrar antes de encerrar.';
                }
                break;
        }

        return [
            'pode' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }

    /**
     * Valida se o processo tem todos os dados obrigatórios preenchidos
     */
    public function validarDadosObrigatorios(Processo $processo): array
    {
        $erros = [];

        // Dados básicos obrigatórios
        if (!$processo->orgao_id) {
            $erros[] = 'Órgão é obrigatório.';
        }

        if (!$processo->modalidade) {
            $erros[] = 'Modalidade é obrigatória.';
        }

        if (!$processo->numero_modalidade) {
            $erros[] = 'Número da modalidade é obrigatório.';
        }

        if (!$processo->objeto_resumido) {
            $erros[] = 'Objeto resumido é obrigatório.';
        }

        if (!$processo->data_hora_sessao_publica) {
            $erros[] = 'Data e hora da sessão pública são obrigatórias.';
        }

        // Validar itens
        if ($processo->itens()->count() === 0) {
            $erros[] = 'O processo deve ter pelo menos um item.';
        }

        // Validar itens obrigatórios
        foreach ($processo->itens as $item) {
            if (!$item->quantidade || $item->quantidade <= 0) {
                $erros[] = "Item #{$item->numero_item}: Quantidade é obrigatória e deve ser maior que zero.";
            }

            if (!$item->unidade) {
                $erros[] = "Item #{$item->numero_item}: Unidade é obrigatória.";
            }

            if (!$item->especificacao_tecnica) {
                $erros[] = "Item #{$item->numero_item}: Especificação técnica é obrigatória.";
            }
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros,
        ];
    }

    /**
     * Valida se pode retroceder status (geralmente não permitido)
     */
    public function podeRetrocederStatus(Processo $processo, string $novoStatus): bool
    {
        $ordemStatus = [
            'participacao' => 1,
            'julgamento_habilitacao' => 2,
            'vencido' => 3,
            'execucao' => 4,
            'pagamento' => 5,
            'encerramento' => 6,
            'arquivado' => 7,
        ];

        $statusAtual = $processo->status;
        $ordemAtual = $ordemStatus[$statusAtual] ?? 0;
        $ordemNova = $ordemStatus[$novoStatus] ?? 0;

        // Permitir retrocesso apenas em casos especiais
        // Por exemplo, de arquivado para qualquer outro (exceto perdido)
        if ($statusAtual === 'arquivado' && $novoStatus !== 'perdido') {
            return true;
        }

        // Não permitir retrocesso normal
        return $ordemNova >= $ordemAtual;
    }
}
