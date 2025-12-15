<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessoItem extends Model
{
    protected $table = 'processo_itens';

    protected $fillable = [
        'processo_id',
        'numero_item',
        'quantidade',
        'unidade',
        'especificacao_tecnica',
        'marca_modelo_referencia',
        'exige_atestado',
        'quantidade_minima_atestado',
        'quantidade_atestado_cap_tecnica',
        'valor_estimado',
        'valor_final_sessao',
        'valor_negociado',
        'classificacao',
        'status_item',
        'chance_arremate',
        'chance_percentual',
        'lembretes',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'valor_final_sessao' => 'decimal:2',
            'valor_negociado' => 'decimal:2',
            'exige_atestado' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    public function formacoesPreco(): HasMany
    {
        return $this->hasMany(FormacaoPreco::class);
    }

    public function getOrcamentoEscolhidoAttribute(): ?Orcamento
    {
        return $this->orcamentos()->where('fornecedor_escolhido', true)->first();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class);
    }

    public function vinculosContrato(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('contrato_id');
    }

    public function vinculosAF(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('autorizacao_fornecimento_id');
    }

    public function vinculosEmpenho(): HasMany
    {
        return $this->hasMany(ProcessoItemVinculo::class)->whereNotNull('empenho_id');
    }
}
