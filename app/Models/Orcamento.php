<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orcamento extends Model
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    protected $fillable = [
        'processo_id', // Novo: para orçamentos vinculados ao processo
        'processo_item_id', // Mantido para compatibilidade (deprecated)
        'fornecedor_id',
        'transportadora_id',
        'custo_produto', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'marca_modelo', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'ajustes_especificacao', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'frete', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'frete_incluido', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'fornecedor_escolhido', // Mantido para compatibilidade (deprecated - usar orcamento_itens)
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'custo_produto' => 'decimal:2',
            'frete' => 'decimal:2',
            'frete_incluido' => 'boolean',
            'fornecedor_escolhido' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function processoItem(): BelongsTo
    {
        return $this->belongsTo(ProcessoItem::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function formacaoPreco(): HasOne
    {
        return $this->hasOne(FormacaoPreco::class);
    }

    // Relacionamento com itens do orçamento (many-to-many)
    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function getCustoTotalAttribute(): float
    {
        // Se tem itens vinculados, calcular soma dos custos
        if ($this->itens()->count() > 0) {
            return $this->itens()->sum(function($item) {
                return $item->custo_produto + ($item->frete_incluido ? 0 : $item->frete);
            });
        }
        
        // Fallback para compatibilidade com estrutura antiga
        return $this->custo_produto + ($this->frete_incluido ? 0 : $this->frete);
    }

    /**
     * Verifica se o orçamento tem múltiplos itens
     */
    public function hasMultipleItems(): bool
    {
        return $this->itens()->count() > 1;
    }

    /**
     * Retorna o item único (para compatibilidade)
     */
    public function getItemUnicoAttribute(): ?OrcamentoItem
    {
        return $this->itens()->first();
    }
}
