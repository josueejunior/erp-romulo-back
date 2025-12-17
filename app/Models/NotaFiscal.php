<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscal extends Model
{
    use SoftDeletes;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'processo_id',
        'empenho_id',
        'tipo',
        'numero',
        'serie',
        'data_emissao',
        'fornecedor_id',
        'transportadora',
        'numero_cte',
        'data_entrega_prevista',
        'data_entrega_realizada',
        'situacao_logistica',
        'valor',
        'custo_produto',
        'custo_frete',
        'custo_total',
        'comprovante_pagamento',
        'arquivo',
        'situacao',
        'data_pagamento',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_entrega_prevista' => 'date',
            'data_entrega_realizada' => 'date',
            'data_pagamento' => 'date',
            'valor' => 'decimal:2',
            'custo_produto' => 'decimal:2',
            'custo_frete' => 'decimal:2',
            'custo_total' => 'decimal:2',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function empenho(): BelongsTo
    {
        return $this->belongsTo(Empenho::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
