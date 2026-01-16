<?php

namespace App\Modules\Processo\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoItemVinculo extends BaseModel
{
    protected $table = 'processo_item_vinculos';

    protected $fillable = [
        'empresa_id',
        'processo_item_id',
        'contrato_id',
        'autorizacao_fornecimento_id',
        'empenho_id',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
        ];
    }

    public function processoItem(): BelongsTo
    {
        return $this->belongsTo(ProcessoItem::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function autorizacaoFornecimento(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoFornecimento::class);
    }

    public function empenho(): BelongsTo
    {
        return $this->belongsTo(Empenho::class);
    }
}





