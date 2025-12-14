<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Processo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // empresa_id removido - cada tenant tem seu próprio banco
        'orgao_id',
        'setor_id',
        'modalidade',
        'numero_modalidade',
        'numero_processo_administrativo',
        'srp',
        'objeto_resumido',
        'data_hora_sessao_publica',
        'endereco_entrega',
        'forma_prazo_entrega',
        'prazo_pagamento',
        'validade_proposta',
        'tipo_selecao_fornecedor',
        'tipo_disputa',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'srp' => 'boolean',
            'data_hora_sessao_publica' => 'datetime',
        ];
    }

    // Relação empresa() removida - cada tenant tem seu próprio banco

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ProcessoItem::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ProcessoDocumento::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
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

    public function getIdentificadorAttribute(): string
    {
        $orgao = $this->orgao->uasg ?? $this->orgao->razao_social;
        return "{$this->numero_modalidade} ({$orgao})";
    }

    public function isEmExecucao(): bool
    {
        return in_array($this->status, ['execucao', 'vencido']);
    }

    public function podeEditar(): bool
    {
        return !$this->isEmExecucao();
    }
}
