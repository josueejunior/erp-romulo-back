<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEmpresaScope;
use App\Database\Schema\Blueprint;

class AutorizacaoFornecimento extends Model
{
    use SoftDeletes, HasEmpresaScope;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;

    protected $table = 'autorizacoes_fornecimento';

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'contrato_id',
        'numero',
        'data',
        'data_adjudicacao',
        'data_homologacao',
        'data_fim_vigencia',
        'condicoes_af',
        'itens_arrematados',
        'valor',
        'saldo',
        'valor_empenhado',
        'situacao',
        'situacao_detalhada',
        'vigente',
        'observacoes',
        'numero_cte',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'data_adjudicacao' => 'date',
            'data_homologacao' => 'date',
            'data_fim_vigencia' => 'date',
            'valor' => 'decimal:2',
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

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
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
        $this->saldo = $this->valor - $totalEmpenhos;
        
        // Atualizar situação detalhada
        if ($totalEmpenhos == 0) {
            $this->situacao_detalhada = 'aguardando_empenho';
        } elseif ($totalEmpenhos < $this->valor) {
            $this->situacao_detalhada = 'parcialmente_atendida';
        } elseif ($this->saldo <= 0) {
            $this->situacao_detalhada = 'concluida';
        } else {
            $this->situacao_detalhada = 'atendendo_empenho';
        }
        
        // Atualizar vigência
        $hoje = now();
        if ($this->data_fim_vigencia && $hoje->isAfter($this->data_fim_vigencia)) {
            $this->vigente = false;
        }
        
        $this->save();
    }
}
