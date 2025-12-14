<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditoriaLog extends Model
{
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'acao',
        'campo',
        'valor_anterior',
        'valor_novo',
        'observacoes',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
