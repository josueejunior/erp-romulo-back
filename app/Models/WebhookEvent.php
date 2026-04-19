<?php

namespace App\Models;

use App\Models\Traits\CentralConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de webhook recebido (tabela central).
 *
 * Serve para: idempotência por (provider, event_type, resource_id), auditoria,
 * retry controlado, e métricas de ingestão de webhooks de payment providers.
 */
class WebhookEvent extends Model
{
    use CentralConnection;

    protected $table = 'webhook_events';

    protected $fillable = [
        'provider',
        'event_type',
        'resource_id',
        'action',
        'payload',
        'headers',
        'status',
        'attempts',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';
}
