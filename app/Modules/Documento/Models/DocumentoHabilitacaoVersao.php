<?php

namespace App\Modules\Documento\Models;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToEmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoHabilitacaoVersao extends BaseModel
{
    use BelongsToEmpresaTrait;

    protected $table = 'documento_habilitacao_versoes';

    /**
     * Desabilitar timestamps automáticos para esta tabela
     * Os campos são gerenciados manualmente no fillable
     */
    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'documento_habilitacao_id',
        'user_id',
        'versao',
        'nome_arquivo',
        'caminho',
        'mime',
        'tamanho_bytes',
        'created_at', // Timestamp padrão Laravel
        'updated_at', // Timestamp padrão Laravel
    ];

    protected function casts(): array
    {
        return [
            'versao' => 'integer',
            'tamanho_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoHabilitacao::class, 'documento_habilitacao_id');
    }
}
