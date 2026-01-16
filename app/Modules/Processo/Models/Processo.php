<?php

namespace App\Modules\Processo\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Concerns\BelongsToEmpresa;
use App\Models\Empresa;
use App\Modules\Orgao\Models\Orgao;
use App\Modules\Orgao\Models\OrgaoResponsavel;
use App\Modules\Orgao\Models\Setor;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Modules\Orcamento\Models\Orcamento;

class Processo extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'orgao_id',
        'orgao_responsavel_id',
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
        'data_recebimento_pagamento',
        'observacoes',
        'motivo_perda',
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
            'data_recebimento_pagamento' => 'date',
            'data_arquivamento' => 'datetime',
        ];
    }

    protected $appends = [
        'identificador',
        'nome_empresa',
        'validade_proposta_calculada',
        'resumo_entregas',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function orgaoResponsavel(): BelongsTo
    {
        return $this->belongsTo(OrgaoResponsavel::class);
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
     * Verifica se há entregas pendentes (itens com quantidade não totalmente vinculada)
     */
    public function temEntregasPendentes(): bool
    {
        // Carrega itens com situação "vencido" (ganhos) que ainda têm quantidade disponível
        return $this->itens()
            ->where('situacao_final', 'vencido')
            ->get()
            ->some(function ($item) {
                return $item->quantidade_disponivel > 0;
            });
    }

    /**
     * Verifica se o processo pode ser finalizado
     * Um processo só pode ser finalizado se todos os itens vencidos estão 100% vinculados
     */
    public function podeSerFinalizado(): bool
    {
        // Se tem entregas pendentes, não pode finalizar
        if ($this->temEntregasPendentes()) {
            return false;
        }
        return true;
    }

    /**
     * Retorna o motivo pelo qual o processo não pode ser finalizado
     */
    public function getMotivoNaoPodeFinalizar(): ?string
    {
        if ($this->temEntregasPendentes()) {
            $itensPendentes = $this->itens()
                ->where('situacao_final', 'vencido')
                ->get()
                ->filter(fn($item) => $item->quantidade_disponivel > 0);
            
            $count = $itensPendentes->count();
            return "{$count} " . ($count === 1 ? 'item possui' : 'itens possuem') . ' entregas parciais pendentes';
        }
        return null;
    }

    /**
     * Accessor: Resumo de entregas (para API)
     */
    public function getResumoEntregasAttribute(): array
    {
        $itensVencidos = $this->itens()->where('situacao_final', 'vencido')->get();
        
        $totalItens = $itensVencidos->count();
        $itensCompletos = $itensVencidos->filter(fn($i) => $i->quantidade_disponivel <= 0)->count();
        $itensPendentes = $totalItens - $itensCompletos;
        
        return [
            'total_itens' => $totalItens,
            'itens_completos' => $itensCompletos,
            'itens_pendentes' => $itensPendentes,
            'pode_finalizar' => $itensPendentes === 0,
            'percentual_completo' => $totalItens > 0 ? round(($itensCompletos / $totalItens) * 100, 1) : 0,
        ];
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
