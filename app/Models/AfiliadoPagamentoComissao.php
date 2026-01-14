<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model para pagamentos de comissões aos afiliados
 */
class AfiliadoPagamentoComissao extends Model
{
    protected $table = 'afiliado_pagamentos_comissoes';

    protected $fillable = [
        'afiliado_id',
        'periodo_competencia',
        'data_pagamento',
        'valor_total',
        'quantidade_comissoes',
        'status',
        'metodo_pagamento',
        'comprovante',
        'observacoes',
        'pago_por',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'periodo_competencia' => 'date',
            'data_pagamento' => 'date',
            'valor_total' => 'decimal:2',
            'quantidade_comissoes' => 'integer',
            'pago_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Afiliado\Models\Afiliado::class, 'afiliado_id');
    }
}







