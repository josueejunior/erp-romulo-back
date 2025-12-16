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
        'link_edital',
        'portal',
        'numero_edital',
        'srp',
        'objeto_resumido',
        'data_hora_sessao_publica',
        'horario_sessao_publica',
        'endereco_entrega',
        'local_entrega_detalhado',
        'forma_entrega',
        'prazo_entrega',
        'forma_prazo_entrega',
        'prazos_detalhados',
        'prazo_pagamento',
        'validade_proposta',
        'validade_proposta_inicio',
        'validade_proposta_fim',
        'tipo_selecao_fornecedor',
        'tipo_disputa',
        'status',
        'status_participacao',
        'observacoes',
        'data_arquivamento',
    ];

    protected function casts(): array
    {
        return [
            'srp' => 'boolean',
            'data_hora_sessao_publica' => 'datetime',
            'horario_sessao_publica' => 'datetime',
            'validade_proposta_inicio' => 'date',
            'validade_proposta_fim' => 'date',
            'data_arquivamento' => 'datetime',
        ];
    }

    protected $appends = [
        'identificador',
        'nome_empresa',
        'validade_proposta_calculada',
    ];

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

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    public function getIdentificadorAttribute(): string
    {
        if (!$this->orgao) {
            return $this->numero_modalidade ?? 'Sem identificação';
        }
        
        $orgao = $this->orgao->uasg ?? $this->orgao->razao_social ?? 'Órgão não identificado';
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

    /**
     * Calcula a validade da proposta proporcional à data de elaboração
     */
    public function getValidadePropostaCalculadaAttribute(): ?string
    {
        if (!$this->validade_proposta_inicio || !$this->validade_proposta_fim) {
            return $this->validade_proposta;
        }

        $inicio = \Carbon\Carbon::parse($this->validade_proposta_inicio);
        $fim = \Carbon\Carbon::parse($this->validade_proposta_fim);
        $hoje = \Carbon\Carbon::now();

        // Se a data de hoje está dentro do período de validade
        if ($hoje->between($inicio, $fim)) {
            $diasRestantes = $hoje->diffInDays($fim);
            return "Válida até {$fim->format('d/m/Y')} ({$diasRestantes} dias restantes)";
        }

        // Se já passou
        if ($hoje->isAfter($fim)) {
            return "Vencida em {$fim->format('d/m/Y')}";
        }

        // Se ainda não começou
        return "Válida de {$inicio->format('d/m/Y')} até {$fim->format('d/m/Y')}";
    }

    /**
     * Verifica se a validade da proposta está vencida
     */
    public function isValidadePropostaVencida(): bool
    {
        if (!$this->validade_proposta_fim) {
            return false;
        }

        return \Carbon\Carbon::now()->isAfter($this->validade_proposta_fim);
    }

    /**
     * Retorna o nome da empresa (do tenant atual)
     */
    public function getNomeEmpresaAttribute(): string
    {
        try {
            if (tenancy()->initialized) {
                $tenant = tenant();
                return $tenant ? ($tenant->razao_social ?? 'Empresa não identificada') : 'Empresa não identificada';
            }
        } catch (\Exception $e) {
            // Se houver erro ao obter tenant, retornar valor padrão
        }
        return 'Empresa não identificada';
    }
}
