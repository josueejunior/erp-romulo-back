<?php

namespace App\Modules\NotaFiscal\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\Fornecedor\Models\Fornecedor;

class NotaFiscal extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'empenho_id',
        'contrato_id',
        'autorizacao_fornecimento_id',
        'tipo',
        'numero',
        'serie',
        'data_emissao',
        'fornecedor_id',
        'transportadora',
        'numero_cte',
        'data_entrega_prevista',
        'data_entrega_realizada',
        'situacao_logistica',
        'valor',
        'custo_produto',
        'custo_frete',
        'custo_total',
        'comprovante_pagamento',
        'arquivo',
        'situacao',
        'data_pagamento',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_entrega_prevista' => 'date',
            'data_entrega_realizada' => 'date',
            'data_pagamento' => 'date',
            'valor' => 'decimal:2',
            'custo_produto' => 'decimal:2',
            'custo_frete' => 'decimal:2',
            'custo_total' => 'decimal:2',
        ];
    }

    /**
     * Calcula custo_total automaticamente se não fornecido
     */
    protected static function booted()
    {
        static::saving(function ($nota) {
            // Calcular custo_total se não fornecido ou se custo_produto/custo_frete mudaram
            if ($nota->isDirty(['custo_produto', 'custo_frete']) || !$nota->custo_total) {
                $produto = $nota->custo_produto ?? 0;
                $frete = $nota->custo_frete ?? 0;
                $nota->custo_total = round($produto + $frete, 2);
            }
        });
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function empenho(): BelongsTo
    {
        return $this->belongsTo(Empenho::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function autorizacaoFornecimento(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoFornecimento::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}

