<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Orcamento extends Model
{
    protected $fillable = [
        'processo_item_id',
        'fornecedor_id',
        'transportadora_id',
        'custo_produto',
        'marca_modelo',
        'ajustes_especificacao',
        'frete',
        'frete_incluido',
        'fornecedor_escolhido',
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

    public function getCustoTotalAttribute(): float
    {
        return $this->custo_produto + ($this->frete_incluido ? 0 : $this->frete);
    }
}
