<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessoItem extends Model
{
    protected $table = 'processo_itens';

    protected $fillable = [
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
        'data_disputa',
        'valor_negociado',
        'classificacao',
        'status_item',
        'situacao_final',
        'chance_arremate',
        'chance_percentual',
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
            'valor_negociado' => 'decimal:2',
            'data_disputa' => 'date',
            'exige_atestado' => 'boolean',
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

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    public function formacoesPreco(): HasMany
    {
        return $this->hasMany(FormacaoPreco::class);
    }

    public function getOrcamentoEscolhidoAttribute(): ?Orcamento
    {
        return $this->orcamentos()->where('fornecedor_escolhido', true)->first();
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

        // Valor faturado = soma das NF-e de saída vinculadas
        // TODO: Implementar quando tiver relação com notas fiscais

        // Valor pago = soma dos pagamentos confirmados
        // TODO: Implementar quando tiver relação com pagamentos

        // Saldo em aberto = valor vencido - valor pago
        $this->saldo_aberto = $this->valor_vencido - $this->valor_pago;

        // Lucro bruto = receita - custos diretos
        $custoTotal = $this->getCustoTotal();
        $this->lucro_bruto = $this->valor_faturado - $custoTotal;

        // Lucro líquido = lucro bruto - custos indiretos
        // TODO: Implementar quando tiver custos indiretos alocados por item
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
