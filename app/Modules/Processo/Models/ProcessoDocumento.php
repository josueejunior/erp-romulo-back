<?php

namespace App\Modules\Processo\Models;

use App\Models\TenantModel;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class ProcessoDocumento extends TenantModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;
    
    protected $table = 'processo_documentos';

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'documento_habilitacao_id',
        'versao_documento_habilitacao_id',
        'documento_custom',
        'titulo_custom',
        'exigido',
        'disponivel_envio',
        'status',
        'nome_arquivo',
        'caminho_arquivo',
        'mime',
        'tamanho_bytes',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'exigido' => 'boolean',
            'disponivel_envio' => 'boolean',
            'documento_custom' => 'boolean',
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

    public function versaoDocumento(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Documento\Models\DocumentoHabilitacaoVersao::class, 'versao_documento_habilitacao_id');
    }
}
