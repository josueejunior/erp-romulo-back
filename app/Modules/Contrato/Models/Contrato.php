<?php

namespace App\Modules\Contrato\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class Contrato extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

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

    /**
     * Vínculos de itens especificamente ligados a este contrato
     */
    public function vinculosItem(): HasMany
    {
        return $this->hasMany(\App\Modules\Processo\Models\ProcessoItemVinculo::class, 'contrato_id');
    }

    public function atualizarSaldo(): void
    {
        // 🔥 CORREÇÃO: Somamos apenas o valor_total dos vínculos de itens deste contrato que possuem empenho.
        $totalEmpenhos = $this->vinculosItem()
            ->whereNotNull('empenho_id')
            ->sum('valor_total') ?? 0;

        $this->valor_empenhado = $totalEmpenhos;
        $this->saldo = $this->valor_total - $totalEmpenhos;
        
        // Atualizar vigência baseado nas datas
        $hoje = now();
        $vencido = $this->data_fim && $hoje->isAfter($this->data_fim);
        
        if ($vencido) {
            $this->vigente = false;
            // Valores permitidos na constraint: vigente, encerrado, cancelado
            $this->situacao = 'encerrado';
        } else {
            $this->vigente = true;
            // Manter como vigente enquanto estiver dentro do prazo
            $this->situacao = 'vigente';
        }
        
        // Usar saveQuietly para evitar loops infinitos com observers
        $this->saveQuietly();
    }

    /**
     * Scope: Contratos com alerta
     * 
     * Regras de negócio:
     * - Vigência vencendo em até 30 dias
     * - Saldo baixo (menor que 10% do valor total)
     * - Contrato vencido mas ainda com saldo
     * 
     * ✅ Reutilizável em qualquer query
     * ✅ Regra de negócio isolada
     */
    public function scopeComAlerta(Builder $query): Builder
    {
        $hoje = Carbon::now();

        return $query->where(function ($q) use ($hoje) {
            // Vigência vencendo em até 30 dias
            $q->where(function ($sub) use ($hoje) {
                $sub->whereBetween('data_fim', [$hoje, $hoje->copy()->addDays(30)])
                    ->where('vigente', true);
            })
            // Saldo baixo (menor que 10% do valor total)
            ->orWhereRaw('saldo < (valor_total * 0.1)')
            // Contrato vencido mas ainda com saldo
            ->orWhere(function ($sub) use ($hoje) {
                $sub->where('data_fim', '<', $hoje)
                    ->where('saldo', '>', 0);
            });
        });
    }
}



