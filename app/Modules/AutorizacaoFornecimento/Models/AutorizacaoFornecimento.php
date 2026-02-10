<?php

namespace App\Modules\AutorizacaoFornecimento\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TenantModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\NotaFiscal\Models\NotaFiscal;

class AutorizacaoFornecimento extends TenantModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

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
        // 🔥 CORREÇÃO: Somar apenas o valor_total dos vínculos de itens desta AF que possuem empenho.
        $totalEmpenhos = \App\Modules\Processo\Models\ProcessoItemVinculo::where('autorizacao_fornecimento_id', $this->id)
            ->whereNotNull('empenho_id')
            ->sum('valor_total') ?? 0;

        $this->valor_empenhado = $totalEmpenhos;
        $this->saldo = $this->valor - $totalEmpenhos;
        
        // Atualizar situação baseada em empenhos
        if ($totalEmpenhos == 0) {
            $this->situacao = 'aguardando_empenho';
            $this->situacao_detalhada = 'aguardando_empenho';
        } elseif ($totalEmpenhos < $this->valor) {
            $this->situacao = 'atendendo';
            $this->situacao_detalhada = 'parcialmente_atendida';
        } else {
            $this->situacao = 'concluida';
            $this->situacao_detalhada = 'concluida';
        }
        
        // Atualizar vigência
        $hoje = now();
        if ($this->data_fim_vigencia && $hoje->isAfter($this->data_fim_vigencia)) {
            $this->vigente = false;
        }
        
        // Usar saveQuietly para evitar loops infinitos com observers
        $this->saveQuietly();
    }
}



