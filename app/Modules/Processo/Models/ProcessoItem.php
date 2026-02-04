<?php

namespace App\Modules\Processo\Models;

use App\Models\BaseModel;
use App\Modules\Orcamento\Models\FormacaoPreco;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\OrcamentoItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Database\Schema\Blueprint;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class ProcessoItem extends BaseModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;
    
    protected $table = 'processo_itens';

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'fornecedor_id',
        'transportadora_id',
        'numero_item',
        'codigo_interno',
        'quantidade',
        'unidade',
        'especificacao_tecnica',
        'marca_modelo_referencia',
        'observacoes_edital',
        'exige_atestado',
        'quantidade_minima_atestado',
        'quantidade_atestado_cap_tecnica',
        'valor_estimado',
        'valor_estimado_total',
        'fonte_valor',
        'valor_minimo_venda',
        'valor_final_sessao',
        'valor_arrematado',
        'data_disputa',
        'valor_negociado',
        'classificacao',
        'status_item',
        'situacao_final',
        'chance_arremate',
        'chance_percentual',
        'tem_chance',
        'lembretes',
        'observacoes',
        'valor_vencido',
        'valor_empenhado',
        'valor_faturado',
        'valor_pago',
        'saldo_aberto',
        'lucro_bruto',
        'lucro_liquido',
        'data_proxima_entrega',
        'observacao_proxima_entrega',
    ];

    protected $appends = [
        'quantidade_vinculada',
        'quantidade_disponivel',
        'percentual_vinculado',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'valor_estimado_total' => 'decimal:2',
            'valor_minimo_venda' => 'decimal:2',
            'valor_final_sessao' => 'decimal:2',
            'valor_arrematado' => 'decimal:2',
            'valor_negociado' => 'decimal:2',
            'data_disputa' => 'date',
            'exige_atestado' => 'boolean',
            'tem_chance' => 'boolean',
            'valor_vencido' => 'decimal:2',
            'valor_empenhado' => 'decimal:2',
            'valor_faturado' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'saldo_aberto' => 'decimal:2',
            'lucro_bruto' => 'decimal:2',
            'lucro_liquido' => 'decimal:2',
            'data_proxima_entrega' => 'date',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fornecedor\Models\Fornecedor::class, 'fornecedor_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fornecedor\Models\Fornecedor::class, 'transportadora_id');
    }

    public function orcamentos(): HasManyThrough
    {
        // Relacionamento através de orcamento_itens (nova estrutura)
        // Usa hasManyThrough para evitar erro se processo_item_id não existir em orcamentos
        $relation = $this->hasManyThrough(
            Orcamento::class,
            OrcamentoItem::class,
            'processo_item_id', // Foreign key on orcamento_itens table
            'id', // Foreign key on orcamentos table
            'id', // Local key on processo_itens table
            'orcamento_id' // Local key on orcamento_itens table
        );
        
        // IMPORTANTE: Remover o scope global ANTES de qualquer filtro
        // O HasEmpresaScope aplica where('empresa_id', ...) automaticamente
        // Mas em hasManyThrough com JOIN, isso causa ambiguidade porque não especifica a tabela
        $relation->withoutGlobalScope('empresa');
        
        // Aplicar o filtro explicitamente na tabela orcamentos
        $empresaId = $this->empresa_id ?? null;
        if ($empresaId) {
            $relation->where('orcamentos.empresa_id', $empresaId)
                     ->whereNotNull('orcamentos.empresa_id');
        }
        
        // Ordenar pela coluna correta de timestamp (criado_em, não created_at)
        // Especificar a tabela explicitamente para evitar ambiguidade
        return $relation->orderBy('orcamentos.' . Blueprint::CREATED_AT, 'desc');
    }

    public function orcamentoItens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function formacoesPreco(): HasMany
    {
        return $this->hasMany(FormacaoPreco::class);
    }

    public function getOrcamentoEscolhidoAttribute(): ?Orcamento
    {
        // Buscar na nova estrutura (orcamento_itens) - estrutura atual
        $orcamentoItem = $this->orcamentoItens()->where('fornecedor_escolhido', true)->first();
        if ($orcamentoItem && $orcamentoItem->orcamento) {
            return $orcamentoItem->orcamento;
        }

        // Fallback: tentar buscar diretamente nos orçamentos (estrutura antiga - compatibilidade)
        // Isso só funcionará se a coluna processo_item_id existir na tabela orcamentos
        try {
            $orcamentoAntigo = Orcamento::where('processo_item_id', $this->id)
                ->where('fornecedor_escolhido', true)
                ->first();
            if ($orcamentoAntigo) {
                return $orcamentoAntigo;
            }
        } catch (\Exception $e) {
            // Se a coluna não existir, ignorar o erro e continuar
        }

        return null;
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class);
    }

    public function vinculosContrato(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('contrato_id');
    }

    public function vinculosAF(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('autorizacao_fornecimento_id');
    }

    public function vinculosEmpenho(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('empenho_id');
    }

    /**
     * Accessor: Quantidade total vinculada (contratos + empenhos + AFs)
     */
    public function getQuantidadeVinculadaAttribute(): float
    {
        return (float) $this->vinculos()->sum('quantidade');
    }

    /**
     * Accessor: Quantidade disponível para vincular
     */
    public function getQuantidadeDisponivelAttribute(): float
    {
        $total = (float) ($this->quantidade ?? 0);
        $vinculada = $this->quantidade_vinculada;
        return max(0, $total - $vinculada);
    }

    /**
     * Accessor: Valor total vinculado
     */
    public function getValorVinculadoAttribute(): float
    {
        return (float) $this->vinculos()->sum('valor_total');
    }

    /**
     * Accessor: Percentual vinculado (0-100)
     */
    public function getPercentualVinculadoAttribute(): float
    {
        $total = (float) ($this->quantidade ?? 0);
        if ($total <= 0) return 0;
        return round(($this->quantidade_vinculada / $total) * 100, 1);
    }

    /**
     * Calcula valor_estimado_total automaticamente
     */
    protected static function booted()
    {
        static::saving(function ($item) {
            // Recalcular valor_estimado_total se quantidade ou valor_estimado mudaram
            if ($item->isDirty(['quantidade', 'valor_estimado'])) {
                $quantidade = $item->quantidade ?? 0;
                $valorUnitario = $item->valor_estimado ?? 0;
                $item->valor_estimado_total = round($quantidade * $valorUnitario, 2);
            }
        });
    }

    /**
     * Retorna a formação de preço ativa (mais recente)
     */
    public function getFormacaoPrecoAtivaAttribute(): ?FormacaoPreco
    {
        return $this->formacoesPreco()
            ->whereHas('orcamento', function($q) {
                $q->where('fornecedor_escolhido', true);
            })
            ->latest()
            ->first();
    }

    /**
     * Calcula o valor mínimo de venda baseado na formação de preços
     */
    public function calcularValorMinimoVenda(): ?float
    {
        $formacaoPreco = $this->formacaoPrecoAtiva;
        if ($formacaoPreco) {
            $this->valor_minimo_venda = $formacaoPreco->preco_minimo;
            $this->save();
            return $formacaoPreco->preco_minimo;
        }
        return null;
    }

    /**
     * Verifica se o item está vencido
     */
    public function isVencido(): bool
    {
        return $this->situacao_final === 'vencido' || 
               ($this->status_item === 'aceito' || $this->status_item === 'aceito_habilitado');
    }

    /**
     * Verifica se o item está perdido
     */
    public function isPerdido(): bool
    {
        return $this->situacao_final === 'perdido' ||
               ($this->status_item === 'desclassificado' || $this->status_item === 'inabilitado');
    }

    /**
     * Atualiza os valores financeiros do item
     */
    public function atualizarValoresFinanceiros(): void
    {
        // 1. Calcular Valor Vencido (potencial total do item)
        if ($this->isVencido()) {
            $valorUnitario = $this->valor_arrematado > 0 ? $this->valor_arrematado : 
                           ($this->valor_negociado > 0 ? $this->valor_negociado : 
                           ($this->valor_final_sessao > 0 ? $this->valor_final_sessao : ($this->valor_estimado ?? 0)));
            
            $this->valor_vencido = round($valorUnitario * ($this->quantidade ?? 1), 2);
        } else {
            $this->valor_vencido = 0;
        }

        // 2. Valor Empenhado (soma de todos os vínculos de empenho)
        $this->valor_empenhado = (float) $this->vinculos()
            ->whereNotNull('empenho_id')
            ->sum('valor_total');

        // 3. Valor Faturado e Pago (Baseado nos Vínculos Diretos de Nota Fiscal)
        // ✅ Prioridade absoluta: Vínculos diretos com Nota Fiscal (tipo saída)
        // Isso elimina erros de rateio proporcional.
        // 3. Valor Faturado e Pago (Baseado nos Vínculos de Nota Fiscal)
        // ✅ Usa a tabela de vínculos (processo_item_vinculos) que suporta 1:N itens
        
        $this->valor_faturado = (float) $this->vinculos()
            ->whereNotNull('nota_fiscal_id')
            ->whereHas('notaFiscal', function($q) {
                $q->where('tipo', 'saida');
            })
            ->sum('valor_total');
            
        // 4. Valor Pago (apenas NFs pagas)
        $this->valor_pago = (float) $this->vinculos()
            ->whereNotNull('nota_fiscal_id')
            ->whereHas('notaFiscal', function($q) {
                $q->where('tipo', 'saida')
                  ->whereIn('situacao', ['paga', 'pago']); // Aceitar variações
            })
            ->sum('valor_total');

        // 🔥 REGRA PARA LEGADOS/PROCESSOS PAGOS: Se o processo estiver marcado como recebido
        if ($this->processo && $this->processo->data_recebimento_pagamento) {
            if ($this->valor_faturado < $this->valor_empenhado) {
                $this->valor_faturado = $this->valor_empenhado;
            }
            if ($this->valor_pago < $this->valor_faturado) {
                $this->valor_pago = $this->valor_faturado;
            }
        }

        $this->valor_faturado = round($this->valor_faturado, 2);
        $this->valor_pago = round($this->valor_pago, 2);

        // 4. Saldo em aberto (pendência de recebimento)
        $this->saldo_aberto = round($this->valor_faturado - $this->valor_pago, 2);
        
        // 5. Lucro bruto (Estimado sobre o total do contrato)
        $custoTotal = (float) $this->getCustoTotal();
        // Usamos valor_vencido para projetar o lucro total do item ganho
        $this->lucro_bruto = round($this->valor_vencido - $custoTotal, 2);
        
        // Lucro realizado (sobre o faturado) seria outra métrica dinâmica
        $lucroRealizado = round($this->valor_faturado - ($custoTotal * ($this->quantidade > 0 ? ($this->valor_faturado / ($this->valor_vencido ?: 1)) : 0)), 2);
        
        $this->lucro_liquido = $this->lucro_bruto;

        // Salvar sem disparar eventos
        $this->saveQuietly();
        
        // Atualizar saldos dos documentos vinculados
        $vinculos = $this->vinculos()->with(['contrato', 'autorizacaoFornecimento'])->get();
        foreach ($vinculos as $v) {
            if ($v->contrato) {
                $v->contrato->atualizarSaldo();
            }
            if ($v->autorizacaoFornecimento) {
                $v->autorizacaoFornecimento->atualizarSaldo();
            }
        }
    }

    /**
     * Calcula o custo total do item (baseado no orçamento escolhido)
     */
    public function getCustoTotal(): float
    {
        $orcamento = $this->orcamentoEscolhido;
        if (!$orcamento) {
            return 0;
        }

        $custoProduto = $orcamento->custo_produto ?? 0;
        $frete = ($orcamento->frete_incluido ? 0 : ($orcamento->frete ?? 0));
        
        return ($custoProduto + $frete) * $this->quantidade;
    }

    /**
     * Retorna o histórico completo de valores do item
     */
    public function getHistoricoValoresAttribute(): array
    {
        return [
            'valor_estimado' => $this->valor_estimado,
            'valor_minimo_venda' => $this->valor_minimo_venda,
            'valor_final_sessao' => $this->valor_final_sessao,
            'valor_negociado' => $this->valor_negociado,
            'valor_vencido' => $this->valor_vencido,
        ];
    }

    /**
     * Retorna a quantidade total já empenhada
     */
    public function getQuantidadeEmpenhadaAttribute(): float
    {
        return $this->vinculosEmpenho()->sum('quantidade');
    }

    /**
     * Retorna a quantidade restante para empenhar
     */
    public function getQuantidadeRestanteAttribute(): float
    {
        return max(0, $this->quantidade - $this->quantidade_empenhada);
    }

    /**
     * Retorna o status atual do item de forma descritiva
     */
    public function getStatusDescricaoAttribute(): string
    {
        if ($this->situacao_final) {
            return $this->situacao_final === 'vencido' ? 'Vencido' : 'Perdido';
        }

        $statusMap = [
            'pendente' => 'Em Análise',
            'aceito' => 'Aceito',
            'aceito_habilitado' => 'Aceito e Habilitado',
            'desclassificado' => 'Desclassificado',
            'inabilitado' => 'Inabilitado',
        ];

        return $statusMap[$this->status_item] ?? 'Desconhecido';
    }
}
