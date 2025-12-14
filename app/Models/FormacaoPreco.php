<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormacaoPreco extends Model
{
    protected $table = 'formacao_precos';

    protected $fillable = [
        'processo_item_id',
        'orcamento_id',
        'custo_produto',
        'frete',
        'percentual_impostos',
        'valor_impostos',
        'percentual_margem',
        'valor_margem',
        'preco_minimo',
        'preco_recomendado',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'custo_produto' => 'decimal:2',
            'frete' => 'decimal:2',
            'percentual_impostos' => 'decimal:2',
            'valor_impostos' => 'decimal:2',
            'percentual_margem' => 'decimal:2',
            'valor_margem' => 'decimal:2',
            'preco_minimo' => 'decimal:2',
            'preco_recomendado' => 'decimal:2',
        ];
    }

    public function processoItem(): BelongsTo
    {
        return $this->belongsTo(ProcessoItem::class);
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }
}
