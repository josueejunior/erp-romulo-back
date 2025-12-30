<?php

namespace App\Modules\Processo\Models;

use App\Models\BaseModel;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoDocumento extends BaseModel
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
