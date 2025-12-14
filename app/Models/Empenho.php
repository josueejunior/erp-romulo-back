<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empenho extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'processo_id',
        'contrato_id',
        'autorizacao_fornecimento_id',
        'numero',
        'data',
        'valor',
        'concluido',
        'data_entrega',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'data_entrega' => 'date',
            'valor' => 'decimal:2',
            'concluido' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function autorizacaoFornecimento(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoFornecimento::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function concluir(): void
    {
        $this->concluido = true;
        $this->data_entrega = now();
        $this->save();

        if ($this->contrato_id) {
            $this->contrato->atualizarSaldo();
        }

        if ($this->autorizacao_fornecimento_id) {
            $this->autorizacaoFornecimento->atualizarSaldo();
        }
    }
}
