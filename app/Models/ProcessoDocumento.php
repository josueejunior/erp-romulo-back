<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoDocumento extends Model
{
    protected $table = 'processo_documentos';

    protected $fillable = [
        'processo_id',
        'documento_habilitacao_id',
        'exigido',
        'disponivel_envio',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'exigido' => 'boolean',
            'disponivel_envio' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function documentoHabilitacao(): BelongsTo
    {
        return $this->belongsTo(DocumentoHabilitacao::class);
    }
}
