<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model para comissões recorrentes de afiliados
 * 
 * Registra cada ciclo de 30 dias de comissão gerada
 */
class AfiliadoComissaoRecorrente extends Model
{
    protected $table = 'afiliado_comissoes_recorrentes';

    protected $fillable = [
        'afiliado_id',
        'afiliado_indicacao_id',
        'tenant_id',
        'empresa_id',
        'assinatura_id',
        'data_inicio_ciclo',
        'data_fim_ciclo',
        'data_pagamento_cliente',
        'valor_pago_cliente',
        'comissao_percentual',
        'valor_comissao',
        'status',
        'data_pagamento_afiliado',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio_ciclo' => 'date',
            'data_fim_ciclo' => 'date',
            'data_pagamento_cliente' => 'date',
            'data_pagamento_afiliado' => 'date',
            'valor_pago_cliente' => 'decimal:2',
            'comissao_percentual' => 'decimal:2',
            'valor_comissao' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Afiliado\Models\Afiliado::class, 'afiliado_id');
    }

    public function indicacao(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Afiliado\Models\AfiliadoIndicacao::class, 'afiliado_indicacao_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}





