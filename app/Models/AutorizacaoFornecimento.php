<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutorizacaoFornecimento extends Model
{
    use SoftDeletes;

    protected $table = 'autorizacoes_fornecimento';

    protected $fillable = [
        'processo_id',
        'contrato_id',
        'numero',
        'data',
        'valor',
        'saldo',
        'situacao',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'saldo' => 'decimal:2',
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

    public function empenhos(): HasMany
    {
        return $this->hasMany(Empenho::class);
    }

    public function atualizarSaldo(): void
    {
        $totalEmpenhos = $this->empenhos()->sum('valor');
        $this->saldo = $this->valor - $totalEmpenhos;
        $this->save();
    }
}
