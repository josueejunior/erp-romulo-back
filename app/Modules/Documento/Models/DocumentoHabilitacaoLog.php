<?php

namespace App\Modules\Documento\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoHabilitacaoLog extends Model
{
    protected $table = 'documento_habilitacao_logs';

    protected $fillable = [
        'empresa_id',
        'documento_habilitacao_id',
        'user_id',
        'acao',
        'ip',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
