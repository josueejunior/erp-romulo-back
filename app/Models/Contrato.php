<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'processo_id',
        'numero',
        'data_inicio',
        'data_fim',
        'valor_total',
        'saldo',
        'situacao',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'valor_total' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function autorizacoesFornecimento(): HasMany
    {
        return $this->hasMany(AutorizacaoFornecimento::class);
    }

    public function empenhos(): HasMany
    {
        return $this->hasMany(Empenho::class);
    }

    public function atualizarSaldo(): void
    {
        $totalEmpenhos = $this->empenhos()->sum('valor');
        $this->saldo = $this->valor_total - $totalEmpenhos;
        $this->save();
    }
}
