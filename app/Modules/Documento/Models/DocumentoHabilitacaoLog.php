<?php

namespace App\Modules\Documento\Models;

use App\Models\BaseModel;
use App\Models\Concerns\HasEmpresaScope;

class DocumentoHabilitacaoLog extends BaseModel
{
    use HasEmpresaScope;
    
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

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
