<?php

namespace App\Modules\Processo\Models;

use App\Models\BaseModel;
use App\Models\FormacaoPreco;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Database\Schema\Blueprint;

class ProcessoItem extends BaseModel
{
    protected $table = 'processo_itens';

    protected $fillable = [
        'empresa_id',
        'processo_id',
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
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
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
        // Valor vencido = valor negociado ou valor final da sessão (se vencido)
        if ($this->isVencido()) {
            $this->valor_vencido = $this->valor_negociado ?? $this->valor_final_sessao ?? 0;
        } else {
            $this->valor_vencido = 0;
        }

        // Valor empenhado = soma dos vínculos com empenhos
        $this->valor_empenhado = $this->vinculosEmpenho()->sum('valor_total');

        // Valor faturado = soma das NF-e de saída vinculadas através dos vínculos
        $valorFaturado = 0;
        // Buscar notas fiscais de saída através dos vínculos (Contrato/AF/Empenho)
        $vinculos = $this->vinculos()->with(['contrato', 'autorizacaoFornecimento', 'empenho'])->get();
        foreach ($vinculos as $vinculo) {
            if ($vinculo->contrato_id && $vinculo->contrato) {
                $valorFaturado += $vinculo->contrato->notasFiscais()
                    ->where('tipo', 'saida')
                    ->sum('valor');
            }
            if ($vinculo->autorizacao_fornecimento_id && $vinculo->autorizacaoFornecimento) {
                $valorFaturado += $vinculo->autorizacaoFornecimento->notasFiscais()
                    ->where('tipo', 'saida')
                    ->sum('valor');
            }
            if ($vinculo->empenho_id && $vinculo->empenho) {
                $valorFaturado += $vinculo->empenho->notasFiscais()
                    ->where('tipo', 'saida')
                    ->sum('valor');
            }
        }
        $this->valor_faturado = round($valorFaturado, 2);

        // Valor pago = soma das NF-e de saída com situação "paga"
        $valorPago = 0;
        foreach ($vinculos as $vinculo) {
            if ($vinculo->contrato_id && $vinculo->contrato) {
                $valorPago += $vinculo->contrato->notasFiscais()
                    ->where('tipo', 'saida')
                    ->where('situacao', 'paga')
                    ->sum('valor');
            }
            if ($vinculo->autorizacao_fornecimento_id && $vinculo->autorizacaoFornecimento) {
                $valorPago += $vinculo->autorizacaoFornecimento->notasFiscais()
                    ->where('tipo', 'saida')
                    ->where('situacao', 'paga')
                    ->sum('valor');
            }
            if ($vinculo->empenho_id && $vinculo->empenho) {
                $valorPago += $vinculo->empenho->notasFiscais()
                    ->where('tipo', 'saida')
                    ->where('situacao', 'paga')
                    ->sum('valor');
            }
        }
        $this->valor_pago = round($valorPago, 2);

        // Saldo em aberto = valor vencido - valor pago
        $this->saldo_aberto = round($this->valor_vencido - $this->valor_pago, 2);

        // Lucro bruto = receita - custos diretos
        $custoTotal = $this->getCustoTotal();
        $this->lucro_bruto = round($this->valor_faturado - $custoTotal, 2);

        // Lucro líquido = lucro bruto - custos indiretos
        // Nota: Custos indiretos são alocados por período, não por item individual
        // Se houver necessidade de alocar custos indiretos por item, implementar aqui
        $this->lucro_liquido = $this->lucro_bruto;

        $this->save();
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
