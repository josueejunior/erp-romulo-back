<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioService
{
    /**
     * Retorna processos para o calendário de disputas
     * Inclui preços mínimos calculados
     */
    public function getCalendarioDisputas(?Carbon $dataInicio = null, ?Carbon $dataFim = null): Collection
    {
        $dataInicio = $dataInicio ?? Carbon::now();
        $dataFim = $dataFim ?? Carbon::now()->addDays(30);

        $processos = Processo::where('status', 'participacao')
            ->whereBetween('data_hora_sessao_publica', [$dataInicio, $dataFim])
            ->with([
                'orgao',
                'setor',
                'itens.orcamentos.formacaoPreco',
                'itens.orcamentos' => function ($query) {
                    $query->where('fornecedor_escolhido', true);
                }
            ])
            ->orderBy('data_hora_sessao_publica')
            ->get();

        return $processos->map(function ($processo) {
            $precosMinimos = $this->calcularPrecosMinimosProcesso($processo);
            
            return [
                'id' => $processo->id,
                'identificador' => $processo->identificador,
                'modalidade' => $processo->modalidade,
                'numero_modalidade' => $processo->numero_modalidade,
                'orgao' => $processo->orgao->razao_social,
                'uasg' => $processo->orgao->uasg,
                'setor' => $processo->setor->nome,
                'data_hora_sessao' => $processo->data_hora_sessao_publica,
                'horario_sessao' => $processo->horario_sessao_publica,
                'objeto_resumido' => $processo->objeto_resumido,
                'link_edital' => $processo->link_edital,
                'portal' => $processo->portal,
                'precos_minimos' => $precosMinimos,
                'total_itens' => $processo->itens->count(),
                'dias_restantes' => Carbon::now()->diffInDays($processo->data_hora_sessao_publica, false),
                'avisos' => $this->gerarAvisosDisputa($processo),
            ];
        });
    }

    /**
     * Calcula os preços mínimos de venda para cada item do processo
     */
    protected function calcularPrecosMinimosProcesso(Processo $processo): array
    {
        $precos = [];

        foreach ($processo->itens as $item) {
            $orcamentoEscolhido = $item->orcamentoEscolhido;
            
            if ($orcamentoEscolhido && $orcamentoEscolhido->formacaoPreco) {
                $formacao = $orcamentoEscolhido->formacaoPreco;
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica, 0, 50) . '...',
                    'preco_minimo' => $formacao->preco_minimo,
                    'preco_recomendado' => $formacao->preco_recomendado,
                    'fornecedor' => $orcamentoEscolhido->fornecedor->razao_social ?? 'N/A',
                ];
            } else {
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica, 0, 50) . '...',
                    'preco_minimo' => null,
                    'preco_recomendado' => null,
                    'fornecedor' => 'Nenhum orçamento escolhido',
                ];
            }
        }

        return $precos;
    }

    /**
     * Gera avisos para o processo na disputa
     */
    protected function gerarAvisosDisputa(Processo $processo): array
    {
        $avisos = [];
        $hoje = Carbon::now();
        $dataSessao = Carbon::parse($processo->data_hora_sessao_publica);

        // Aviso de proximidade
        $diasRestantes = $hoje->diffInDays($dataSessao, false);
        if ($diasRestantes <= 3 && $diasRestantes >= 0) {
            $avisos[] = [
                'tipo' => 'proximidade',
                'mensagem' => "Disputa em {$diasRestantes} dia(s)",
                'prioridade' => $diasRestantes == 0 ? 'alta' : 'media',
            ];
        }

        // Aviso de documentos vencidos
        $documentosVencidos = $processo->documentos()
            ->whereHas('documentoHabilitacao', function ($query) {
                $query->where('data_validade', '<', now())
                    ->where('ativo', true);
            })
            ->count();

        if ($documentosVencidos > 0) {
            $avisos[] = [
                'tipo' => 'documentos',
                'mensagem' => "{$documentosVencidos} documento(s) vencido(s)",
                'prioridade' => 'alta',
            ];
        }

        // Aviso de itens sem orçamento escolhido
        $itensSemOrcamento = $processo->itens()
            ->whereDoesntHave('orcamentos', function ($query) {
                $query->where('fornecedor_escolhido', true);
            })
            ->count();

        if ($itensSemOrcamento > 0) {
            $avisos[] = [
                'tipo' => 'orcamentos',
                'mensagem' => "{$itensSemOrcamento} item(ns) sem orçamento escolhido",
                'prioridade' => 'media',
            ];
        }

        // Aviso de itens sem formação de preço
        $itensSemFormacao = $processo->itens()
            ->whereHas('orcamentos', function ($query) {
                $query->where('fornecedor_escolhido', true);
            })
            ->whereDoesntHave('formacoesPreco')
            ->count();

        if ($itensSemFormacao > 0) {
            $avisos[] = [
                'tipo' => 'formacao_preco',
                'mensagem' => "{$itensSemFormacao} item(ns) sem formação de preço",
                'prioridade' => 'alta',
            ];
        }

        return $avisos;
    }

    /**
     * Retorna processos para o calendário de julgamento
     * Inclui lembretes e classificações
     */
    public function getCalendarioJulgamento(?Carbon $dataInicio = null, ?Carbon $dataFim = null): Collection
    {
        $dataInicio = $dataInicio ?? Carbon::now();
        $dataFim = $dataFim ?? Carbon::now()->addDays(30);

        $processos = Processo::where('status', 'julgamento_habilitacao')
            ->with([
                'orgao',
                'setor',
                'itens' => function ($query) {
                    $query->whereNotNull('lembretes')
                        ->orWhereNotNull('classificacao');
                }
            ])
            ->orderBy('data_hora_sessao_publica')
            ->get();

        return $processos->map(function ($processo) {
            $itensComLembrete = $processo->itens()
                ->whereNotNull('lembretes')
                ->get()
                ->map(function ($item) {
                    return [
                        'numero_item' => $item->numero_item,
                        'lembrete' => $item->lembretes,
                        'classificacao' => $item->classificacao,
                        'status_item' => $item->status_item,
                        'chance_arremate' => $item->chance_arremate,
                    ];
                });

            return [
                'id' => $processo->id,
                'identificador' => $processo->identificador,
                'modalidade' => $processo->modalidade,
                'numero_modalidade' => $processo->numero_modalidade,
                'orgao' => $processo->orgao->razao_social,
                'setor' => $processo->setor->nome,
                'data_sessao' => $processo->data_hora_sessao_publica,
                'itens_com_lembrete' => $itensComLembrete,
                'total_itens' => $processo->itens->count(),
                'itens_aceitos' => $processo->itens()->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count(),
                'itens_desclassificados' => $processo->itens()->whereIn('status_item', ['desclassificado', 'inabilitado'])->count(),
            ];
        });
    }

    /**
     * Retorna processos com avisos urgentes
     */
    public function getAvisosUrgentes(): array
    {
        $hoje = Carbon::now();
        $proximos3Dias = $hoje->copy()->addDays(3);

        $processosDisputa = Processo::where('status', 'participacao')
            ->whereBetween('data_hora_sessao_publica', [$hoje, $proximos3Dias])
            ->with(['orgao', 'setor'])
            ->get();

        $avisos = [];

        foreach ($processosDisputa as $processo) {
            $avisosProcesso = $this->gerarAvisosDisputa($processo);
            if (!empty($avisosProcesso)) {
                $avisos[] = [
                    'processo_id' => $processo->id,
                    'processo_identificador' => $processo->identificador,
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'avisos' => $avisosProcesso,
                ];
            }
        }

        return $avisos;
    }
}

