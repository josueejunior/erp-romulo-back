<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class Orcamento extends BaseModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    protected $fillable = [
        'empresa_id',
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
        // Verificar se a relação já está carregada
        if ($this->relationLoaded('itens') && $this->itens->count() > 0) {
            return $this->itens->sum(function($item) {
                return (float)($item->custo_produto ?? 0) + ((($item->frete_incluido ?? false) ? 0 : (float)($item->frete ?? 0)));
            });
        }
        
        // Se não tem itens carregados, verificar se existe pelo menos um item
        $count = $this->itens()->count();
        if ($count > 0) {
            // Carregar itens e calcular na collection
            $itens = $this->itens()->get();
            return $itens->sum(function($item) {
                return (float)($item->custo_produto ?? 0) + ((($item->frete_incluido ?? false) ? 0 : (float)($item->frete ?? 0)));
            });
        }
        
        // Fallback para compatibilidade com estrutura antiga
        return (float)($this->custo_produto ?? 0) + ((($this->frete_incluido ?? false) ? 0 : (float)($this->frete ?? 0)));
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
