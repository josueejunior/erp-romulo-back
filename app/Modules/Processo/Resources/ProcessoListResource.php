<?php

namespace App\Modules\Processo\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Modules\Processo\Resources\ProcessoItemResource;

class ProcessoListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $hoje = Carbon::now();
        $dataSessao = $this->data_hora_sessao_publica ? Carbon::parse($this->data_hora_sessao_publica) : null;
        
        // Calcular fase atual
        $faseAtual = $this->calcularFaseAtual();
        
        // Calcular próxima data
        $proximaData = $this->calcularProximaData();
        
        // Verificar alertas
        $alertas = $this->calcularAlertas();
        
        // Calcular valores
        $valores = $this->calcularValores();
        
        // Calcular resultado
        $resultado = $this->calcularResultado();

        return [
            'id' => $this->id,
            'identificador' => $this->identificador,
            'numero_modalidade' => $this->numero_modalidade,
            'modalidade' => $this->modalidade,
            'numero_processo_administrativo' => $this->numero_processo_administrativo,
            'orgao' => $this->when(
                $this->relationLoaded('orgao') && $this->orgao,
                [
                    'id' => $this->orgao->id ?? null,
                    'uasg' => $this->orgao->uasg ?? null,
                    'razao_social' => $this->orgao->razao_social ?? null,
                ],
                null
            ),
            'setor' => $this->when(
                $this->relationLoaded('setor') && $this->setor,
                [
                    'id' => $this->setor->id ?? null,
                    'nome' => $this->setor->nome ?? null,
                ],
                null
            ),
            'objeto_resumido' => $this->objeto_resumido,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'fase_atual' => $faseAtual,
            'data_sessao_publica' => $this->data_hora_sessao_publica?->format('Y-m-d H:i:s'),
            'data_sessao_publica_formatted' => $this->data_hora_sessao_publica?->format('d/m/Y H:i'),
            'proxima_data' => $proximaData,
            'alertas' => $alertas,
            'tem_alerta' => !empty($alertas),
            'valores' => $valores,
            'resultado' => $resultado,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            // Incluir itens com orçamentos quando carregados
            'itens' => $this->when(
                $this->relationLoaded('itens'),
                fn() => ProcessoItemResource::collection($this->itens)
            ),
        ];
    }

    protected function calcularFaseAtual(): string
    {
        try {
            switch ($this->status) {
                case 'participacao':
                    $hoje = Carbon::now();
                    $dataSessao = $this->data_hora_sessao_publica ? Carbon::parse($this->data_hora_sessao_publica) : null;
                    
                    if ($dataSessao && $hoje->isAfter($dataSessao)) {
                        return 'Aguardando registro de disputa';
                    }
                    
                    return 'Disputa';
                    
                case 'julgamento_habilitacao':
                    $itens = $this->relationLoaded('itens') ? ($this->itens ?? collect()) : collect();
                    $itensAceitos = $itens->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count();
                    $itensPendentes = $itens->where('status_item', 'pendente')->count();
                    
                    if ($itensAceitos > 0) {
                        return 'Negociação';
                    }
                    if ($itensPendentes > 0) {
                        return 'Aguardando julgamento';
                    }
                    return 'Aguardando classificação';
                    
                case 'execucao':
                    $empenhos = $this->relationLoaded('empenhos') ? ($this->empenhos ?? collect()) : collect();
                    $contratos = $this->relationLoaded('contratos') ? ($this->contratos ?? collect()) : collect();
                    
                    if ($empenhos->count() > 0) {
                        return 'Atendendo empenhos';
                    }
                    if ($contratos->count() > 0) {
                        return 'Executando contrato';
                    }
                    return 'Aguardando contrato/AF';
                    
                case 'vencido':
                    return 'Aguardando execução';
                    
                case 'pagamento':
                    return 'Aguardando pagamento';
                    
                case 'encerramento':
                    return 'Aguardando encerramento';
                    
                case 'perdido':
                case 'arquivado':
                    return 'Finalizado';
                    
                default:
                    return 'Indefinido';
            }
        } catch (\Exception $e) {
            \Log::warning('Erro ao calcular fase atual', [
                'processo_id' => $this->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
            return 'Indefinido';
        }
    }

    protected function calcularProximaData(): ?array
    {
        try {
            $hoje = Carbon::now();
            
            switch ($this->status) {
                case 'participacao':
                    if ($this->data_hora_sessao_publica) {
                        $dataSessao = Carbon::parse($this->data_hora_sessao_publica);
                        return [
                            'data' => $dataSessao->format('d/m/Y'),
                            'hora' => $dataSessao->format('H:i'),
                            'tipo' => 'Sessão pública',
                            'urgente' => $hoje->diffInDays($dataSessao, false) <= 3,
                        ];
                    }
                    break;
                    
                case 'julgamento_habilitacao':
                    // Próximo lembrete ou prazo de julgamento
                    $itens = $this->relationLoaded('itens') ? ($this->itens ?? collect()) : collect();
                    $itensComLembrete = $itens->whereNotNull('lembretes')->sortByDesc('updated_at')->first();
                    
                    if ($itensComLembrete && $itensComLembrete->updated_at) {
                        return [
                            'data' => $itensComLembrete->updated_at->format('d/m/Y'),
                            'hora' => null,
                            'tipo' => 'Lembrete de julgamento',
                            'urgente' => false,
                        ];
                    }
                    break;
                    
                case 'execucao':
                    // Próximo empenho ou entrega
                    $empenhos = $this->relationLoaded('empenhos') ? ($this->empenhos ?? collect()) : collect();
                    $proximoEmpenho = $empenhos
                        ->whereNotNull('prazo_entrega_calculado')
                        ->where('concluido', false)
                        ->sortBy('prazo_entrega_calculado')
                        ->first();
                    
                    if ($proximoEmpenho && $proximoEmpenho->prazo_entrega_calculado) {
                        $prazo = Carbon::parse($proximoEmpenho->prazo_entrega_calculado);
                        return [
                            'data' => $prazo->format('d/m/Y'),
                            'hora' => null,
                            'tipo' => 'Prazo de entrega',
                            'urgente' => $hoje->diffInDays($prazo, false) <= 7,
                        ];
                    }
                    break;
                    
                case 'pagamento':
                    // Próximo pagamento pendente
                    $notasFiscais = $this->relationLoaded('notasFiscais') ? ($this->notasFiscais ?? collect()) : collect();
                    $proximaNotaPendente = $notasFiscais
                        ->where('pago', false)
                        ->whereNotNull('data_vencimento')
                        ->sortBy('data_vencimento')
                        ->first();
                    
                    if ($proximaNotaPendente && $proximaNotaPendente->data_vencimento) {
                        $vencimento = Carbon::parse($proximaNotaPendente->data_vencimento);
                        return [
                            'data' => $vencimento->format('d/m/Y'),
                            'hora' => null,
                            'tipo' => 'Vencimento de pagamento',
                            'urgente' => $hoje->diffInDays($vencimento, false) <= 7,
                        ];
                    }
                    break;
                    
                case 'encerramento':
                    // Data de encerramento ou arquivamento
                    if ($this->data_arquivamento) {
                        $dataEncerramento = Carbon::parse($this->data_arquivamento);
                        return [
                            'data' => $dataEncerramento->format('d/m/Y'),
                            'hora' => null,
                            'tipo' => 'Encerramento',
                            'urgente' => false,
                        ];
                    }
                    break;
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::warning('Erro ao calcular próxima data', [
                'processo_id' => $this->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function calcularAlertas(): array
    {
        try {
            $alertas = [];
            $hoje = Carbon::now();
            
            // Alerta: Sessão hoje
            if ($this->status === 'participacao' && $this->data_hora_sessao_publica) {
                $dataSessao = Carbon::parse($this->data_hora_sessao_publica);
                if ($dataSessao->isToday()) {
                    $alertas[] = [
                        'tipo' => 'sessao_hoje',
                        'prioridade' => 'alta',
                        'mensagem' => 'Sessão pública hoje',
                    ];
                } elseif ($dataSessao->isPast() && $this->status === 'participacao') {
                    $alertas[] = [
                        'tipo' => 'sessao_passou',
                        'prioridade' => 'alta',
                        'mensagem' => 'Sessão pública já aconteceu - registrar disputa',
                    ];
                }
            }
            
            // Alerta: Julgamento em andamento há muito tempo
            if ($this->status === 'julgamento_habilitacao') {
                $diasSemAtualizacao = $hoje->diffInDays($this->updated_at);
                if ($diasSemAtualizacao > 7) {
                    $alertas[] = [
                        'tipo' => 'julgamento_parado',
                        'prioridade' => 'media',
                        'mensagem' => "Julgamento sem atualização há {$diasSemAtualizacao} dias",
                    ];
                }
            }
            
            // Alerta: Prazo de entrega próximo
            if ($this->status === 'execucao') {
                $empenhos = $this->relationLoaded('empenhos') ? ($this->empenhos ?? collect()) : collect();
                $empenhosAtrasados = $empenhos->where('situacao', 'atrasado')->count();
                
                if ($empenhosAtrasados > 0) {
                    $alertas[] = [
                        'tipo' => 'entrega_atrasada',
                        'prioridade' => 'alta',
                        'mensagem' => "{$empenhosAtrasados} empenho(s) com entrega atrasada",
                    ];
                }
                
                $empenhosProximos = $empenhos
                    ->whereNotNull('prazo_entrega_calculado')
                    ->where('concluido', false)
                    ->filter(function ($empenho) use ($hoje) {
                        try {
                            $prazo = Carbon::parse($empenho->prazo_entrega_calculado);
                            return $prazo->lte($hoje->copy()->addDays(7)) && $prazo->gt($hoje);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->count();
                
                if ($empenhosProximos > 0) {
                    $alertas[] = [
                        'tipo' => 'prazo_proximo',
                        'prioridade' => 'media',
                        'mensagem' => "{$empenhosProximos} empenho(s) com prazo próximo",
                    ];
                }
            }
            
            // Alerta: Documentos vencidos
            $documentos = $this->relationLoaded('documentos') ? ($this->documentos ?? collect()) : collect();
            $documentosVencidos = $documentos
                ->filter(function ($doc) {
                    try {
                        $docHabilitacao = $doc->relationLoaded('documentoHabilitacao') 
                            ? $doc->documentoHabilitacao 
                            : null;
                        return $docHabilitacao 
                            && $docHabilitacao->data_validade 
                            && Carbon::parse($docHabilitacao->data_validade)->isPast()
                            && ($docHabilitacao->ativo ?? true);
                    } catch (\Exception $e) {
                        return false;
                    }
                })
                ->count();
            
            if ($documentosVencidos > 0) {
                $alertas[] = [
                    'tipo' => 'documentos_vencidos',
                    'prioridade' => 'alta',
                    'mensagem' => "{$documentosVencidos} documento(s) vencido(s)",
                ];
            }
            
            return $alertas;
        } catch (\Exception $e) {
            \Log::warning('Erro ao calcular alertas', [
                'processo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function calcularValores(): array
    {
        try {
            $itens = $this->relationLoaded('itens') ? ($this->itens ?? collect()) : collect();
            $valorEstimado = $itens->sum('valor_estimado') ?? 0;
            
            // Valor mínimo (menor preço mínimo das formações de preço)
            $valorMinimo = null;
            $itensComFormacao = $itens->filter(function ($item) {
                return $item->relationLoaded('formacoesPreco') && $item->formacoesPreco->count() > 0;
            });
            
            if ($itensComFormacao->count() > 0) {
                $precosMinimos = $itensComFormacao->flatMap(function ($item) {
                    return $item->formacoesPreco->pluck('preco_minimo');
                });
                $valorMinimo = $precosMinimos->min();
            }
            
            // Valor vencido (se já venceu)
            $valorVencido = null;
            if (in_array($this->status, ['execucao', 'vencido'])) {
                $itensVencidos = $itens->whereIn('status_item', ['aceito', 'aceito_habilitado']);
                
                $valorVencido = $itensVencidos->sum(function ($item) {
                    return $item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0;
                });
            }
            
            return [
                'estimado' => round($valorEstimado, 2),
                'minimo' => $valorMinimo ? round($valorMinimo, 2) : null,
                'vencido' => $valorVencido ? round($valorVencido, 2) : null,
            ];
        } catch (\Exception $e) {
            \Log::warning('Erro ao calcular valores', [
                'processo_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'estimado' => 0,
                'minimo' => null,
                'vencido' => null,
            ];
        }
    }

    protected function calcularResultado(): string
    {
        try {
            if ($this->status === 'perdido' || $this->status === 'arquivado') {
                return 'Perdido';
            }
            
            if (in_array($this->status, ['execucao', 'vencido', 'pagamento', 'encerramento'])) {
                return 'Vencido';
            }
            
            if ($this->status === 'julgamento_habilitacao') {
                $itens = $this->relationLoaded('itens') ? ($this->itens ?? collect()) : collect();
                $itensAceitos = $itens->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count();
                if ($itensAceitos > 0) {
                    return 'Em andamento';
                }
            }
            
            return 'Em andamento';
        } catch (\Exception $e) {
            \Log::warning('Erro ao calcular resultado', [
                'processo_id' => $this->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
            return 'Em andamento';
        }
    }

    protected function getStatusLabel(): string
    {
        $labels = [
            'participacao' => 'Em Participação',
            'julgamento_habilitacao' => 'Em Julgamento',
            'execucao' => 'Em Execução',
            'vencido' => 'Vencido',
            'pagamento' => 'Em Pagamento',
            'encerramento' => 'Em Encerramento',
            'perdido' => 'Perdido',
            'arquivado' => 'Arquivado',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }
}

