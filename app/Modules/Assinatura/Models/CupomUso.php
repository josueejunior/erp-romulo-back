<?php

namespace App\Modules\Assinatura\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CupomUso extends Model
{
    protected $table = 'cupons_uso';

    protected $fillable = [
        'cupom_id',
        'tenant_id',
        'assinatura_id',
        'valor_desconto_aplicado',
        'valor_original',
        'valor_final',
        'usado_em',
    ];

    protected $casts = [
        'valor_desconto_aplicado' => 'decimal:2',
        'valor_original' => 'decimal:2',
        'valor_final' => 'decimal:2',
        'usado_em' => 'datetime',
    ];

    /**
     * Relacionamento com cupom
     */
    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class, 'cupom_id');
    }

    /**
     * Relacionamento com assinatura
     */
    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class, 'assinatura_id');
    }
}
