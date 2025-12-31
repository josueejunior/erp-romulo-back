<?php

namespace App\Modules\Documento\Models;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToEmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoHabilitacaoVersao extends BaseModel
{
    use BelongsToEmpresaTrait;

    protected $table = 'documento_habilitacao_versoes';

    protected $fillable = [
        'empresa_id',
        'documento_habilitacao_id',
        'user_id',
        'versao',
        'nome_arquivo',
        'caminho',
        'mime',
        'tamanho_bytes',
    ];

    protected function casts(): array
    {
        return [
            'versao' => 'integer',
            'tamanho_bytes' => 'integer',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoHabilitacao::class, 'documento_habilitacao_id');
    }
}
