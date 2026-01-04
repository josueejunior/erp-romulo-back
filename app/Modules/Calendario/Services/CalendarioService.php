<?php

namespace App\Modules\Calendario\Services;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioService
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Retorna processos para o calendário de disputas
     * Inclui preços mínimos calculados
     */
    public function getCalendarioDisputas(?Carbon $dataInicio = null, ?Carbon $dataFim = null, ?int $empresaId = null): Collection
    {
        // Se não especificado, buscar disputas dos próximos 60 dias
        $dataInicio = $dataInicio ?? Carbon::now();
        $dataFim = $dataFim ?? Carbon::now()->addDays(60);

        if (!$empresaId) {
            throw new \InvalidArgumentException('empresa_id é obrigatório para buscar processos do calendário');
        }

        $with = [
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->with([
                    'orcamentos.formacaoPreco',
                    'orcamentos.fornecedor',
                    'orcamentoItens.orcamento.fornecedor',
                    'orcamentoItens.formacaoPreco'
                ]);
            }
        ];

        // Buscar processos com data de sessão dentro do período
        $filtrosComData = [
            'empresa_id' => $empresaId,
            'status' => 'participacao',
            'data_hora_sessao_publica_inicio' => $dataInicio,
            'data_hora_sessao_publica_fim' => $dataFim,
        ];
        $processosComData = $this->processoRepository->buscarModelosComFiltros($filtrosComData, $with);
        
        // Buscar TODOS os processos em participação (incluindo os sem data)
        // O calendário deve mostrar processos em participação mesmo sem data definida
        $filtrosSemData = [
            'empresa_id' => $empresaId,
            'status' => 'participacao',
        ];
        $todosProcessosParticipacao = $this->processoRepository->buscarModelosComFiltros($filtrosSemData, $with);
        
        // Filtrar apenas os que não têm data de sessão ou têm status especial
        $processosSemDataOuPendentes = $todosProcessosParticipacao->filter(function ($processo) {
            return $processo->data_hora_sessao_publica === null 
                || in_array($processo->status_participacao, ['adiado', 'suspenso', 'cancelado']);
        });
        
        // Combinar e remover duplicatas
        $processos = $processosComData->merge($processosSemDataOuPendentes)->unique('id');

        return $processos->map(function ($processo) {
            $precosMinimos = $this->calcularPrecosMinimosProcesso($processo);
            
            return [
                'id' => $processo->id,
                'identificador' => $processo->identificador,
                'modalidade' => $processo->modalidade,
                'numero_modalidade' => $processo->numero_modalidade,
                'orgao' => $processo->orgao ? $processo->orgao->razao_social : null,
                'uasg' => $processo->orgao ? $processo->orgao->uasg : null,
                'setor' => $processo->setor ? $processo->setor->nome : null,
                'data_hora_sessao' => $processo->data_hora_sessao_publica,
                'horario_sessao' => $processo->horario_sessao_publica,
                'objeto_resumido' => $processo->objeto_resumido,
                'link_edital' => $processo->link_edital,
                'portal' => $processo->portal,
                'precos_minimos' => $precosMinimos,
                'total_itens' => $processo->itens->count(),
                'dias_restantes' => $processo->data_hora_sessao_publica 
                    ? Carbon::now()->diffInDays($processo->data_hora_sessao_publica, false) 
                    : null,
                'status_participacao' => $processo->status_participacao ?? 'normal',
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
            // Primeiro tentar buscar na estrutura antiga (compatibilidade)
            $orcamentoEscolhido = $item->orcamentos->firstWhere('fornecedor_escolhido', true);
            $formacao = null;
            $fornecedorNome = null;

            if ($orcamentoEscolhido && $orcamentoEscolhido->formacaoPreco) {
                $formacao = $orcamentoEscolhido->formacaoPreco;
                $fornecedorNome = $orcamentoEscolhido->fornecedor->razao_social ?? 'N/A';
            } else {
                // Se não encontrou na estrutura antiga, buscar na nova (orcamento_itens)
                $orcamentoItemEscolhido = $item->orcamentoItens->firstWhere('fornecedor_escolhido', true);
                if ($orcamentoItemEscolhido) {
                    if ($orcamentoItemEscolhido->formacaoPreco) {
                        $formacao = $orcamentoItemEscolhido->formacaoPreco;
                    }
                    $fornecedorNome = $orcamentoItemEscolhido->orcamento->fornecedor->razao_social ?? 'N/A';
                }
            }

            if ($formacao) {
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica, 0, 50) . '...',
                    'preco_minimo' => $formacao->preco_minimo,
                    'preco_recomendado' => $formacao->preco_recomendado,
                    'fornecedor' => $fornecedorNome ?? 'N/A',
                ];
            } else {
                // Se não tem orçamento escolhido, usar valor estimado como fallback
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica, 0, 50) . '...',
                    'preco_minimo' => $item->valor_estimado,
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
    public function getCalendarioJulgamento(?Carbon $dataInicio = null, ?Carbon $dataFim = null, ?int $empresaId = null): Collection
    {
        $dataInicio = $dataInicio ?? Carbon::now();
        $dataFim = $dataFim ?? Carbon::now()->addDays(30);

        if (!$empresaId) {
            throw new \InvalidArgumentException('empresa_id é obrigatório para buscar processos do calendário');
        }

        $filtros = [
            'empresa_id' => $empresaId,
            'status' => 'julgamento_habilitacao',
            'data_hora_sessao_publica_inicio' => $dataInicio,
            'data_hora_sessao_publica_fim' => $dataFim,
        ];

        $with = [
            'orgao',
            'setor',
            'itens' => function ($query) {
                // Incluir todos os itens do processo em julgamento
                // Não filtrar apenas por lembretes, pois processos podem estar em julgamento sem lembretes
            }
        ];

        // Buscar modelos Eloquent com relacionamentos
        $processos = $this->processoRepository->buscarModelosComFiltros($filtros, $with);

        return $processos->map(function ($processo) {
            // Incluir todos os itens do processo, não apenas os com lembretes
            $itensComLembrete = $processo->itens->map(function ($item) {
                return [
                    'numero_item' => $item->numero_item,
                    'lembrete' => $item->lembretes,
                    'classificacao' => $item->classificacao,
                    'status_item' => $item->status_item,
                    'chance_arremate' => $item->chance_arremate,
                    'tem_chance' => $item->tem_chance ?? true,
                    'chance_percentual' => $item->chance_percentual,
                ];
            });

            return [
                'id' => $processo->id,
                'identificador' => $processo->identificador,
                'modalidade' => $processo->modalidade,
                'numero_modalidade' => $processo->numero_modalidade,
                'orgao' => $processo->orgao ? $processo->orgao->razao_social : null,
                'uasg' => $processo->orgao ? $processo->orgao->uasg : null,
                'setor' => $processo->setor ? $processo->setor->nome : null,
                'data_hora_sessao_publica' => $processo->data_hora_sessao_publica,
                'objeto_resumido' => $processo->objeto_resumido,
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
    public function getAvisosUrgentes(?int $empresaId = null): array
    {
        if (!$empresaId) {
            throw new \InvalidArgumentException('empresa_id é obrigatório para buscar avisos urgentes');
        }

        $hoje = Carbon::now();
        $proximos3Dias = $hoje->copy()->addDays(3);

        $filtros = [
            'empresa_id' => $empresaId,
            'status' => 'participacao',
            'data_hora_sessao_publica_inicio' => $hoje,
            'data_hora_sessao_publica_fim' => $proximos3Dias,
        ];

        $with = ['orgao', 'setor'];

        // Buscar modelos Eloquent com relacionamentos
        $processosDisputa = $this->processoRepository->buscarModelosComFiltros($filtros, $with);

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

