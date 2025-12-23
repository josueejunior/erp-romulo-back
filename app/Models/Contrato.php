<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEmpresaScope;
use App\Database\Schema\Blueprint;

class Contrato extends Model
{
    use SoftDeletes, HasEmpresaScope;

    const DELETED_AT = Blueprint::DELETED_AT;

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'numero',
        'data_inicio',
        'data_fim',
        'data_assinatura',
        'valor_total',
        'saldo',
        'valor_empenhado',
        'condicoes_comerciais',
        'condicoes_tecnicas',
        'locais_entrega',
        'prazos_contrato',
        'regras_contrato',
        'situacao',
        'vigente',
        'observacoes',
        'arquivo_contrato',
        'numero_cte',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'data_assinatura' => 'date',
            'valor_total' => 'decimal:2',
            'saldo' => 'decimal:2',
            'valor_empenhado' => 'decimal:2',
            'vigente' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function atualizarSaldo(): void
    {
        $totalEmpenhos = $this->empenhos()->sum('valor');
        $this->valor_empenhado = $totalEmpenhos;
        $this->saldo = $this->valor_total - $totalEmpenhos;
        
        // Atualizar vigência baseado nas datas
        $hoje = now();
        if ($this->data_fim && $hoje->isAfter($this->data_fim)) {
            $this->vigente = false;
        }
        
        $this->save();
    }
}
