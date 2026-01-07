<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Tenant;

/**
 * Model para logs de pagamento (auditoria)
 * 
 * Registra todas as tentativas de pagamento para rastreabilidade
 */
class PaymentLog extends BaseModel
{
    use HasTimestampsCustomizados;

    protected $table = 'payment_logs';

    protected $fillable = [
        'tenant_id',
        'plano_id',
        'valor',
        'periodo',
        'status', // 'pending', 'approved', 'rejected', 'failed'
        'external_id', // ID no gateway (Mercado Pago)
        'idempotency_key', // Chave de idempotência
        'metodo_pagamento',
        'dados_requisicao', // JSON com dados da requisição
        'dados_resposta', // JSON com resposta do gateway
        'erro', // Mensagem de erro se houver
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'valor' => 'decimal:2',
            'dados_requisicao' => 'array',
            'dados_resposta' => 'array',
        ]);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }
}




