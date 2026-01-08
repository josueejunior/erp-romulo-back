<?php

namespace App\Modules\Afiliado\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model de Indicação de Afiliado (Tabela Central)
 * 
 * Registra cada empresa indicada por um afiliado,
 * com histórico de status e valores de comissão.
 */
class AfiliadoIndicacao extends Model
{
    protected $table = 'afiliado_indicacoes';

    protected $fillable = [
        'afiliado_id',
        'tenant_id',
        'empresa_id',
        'codigo_usado',
        'desconto_aplicado',
        'comissao_percentual',
        'plano_id',
        'plano_nome',
        'valor_plano_original',
        'valor_plano_com_desconto',
        'valor_comissao',
        'status',
        'indicado_em',
        'primeira_assinatura_em',
        'cancelado_em',
        'comissao_paga',
        'comissao_paga_em',
    ];

    protected function casts(): array
    {
        return [
            'desconto_aplicado' => 'decimal:2',
            'comissao_percentual' => 'decimal:2',
            'valor_plano_original' => 'decimal:2',
            'valor_plano_com_desconto' => 'decimal:2',
            'valor_comissao' => 'decimal:2',
            'comissao_paga' => 'boolean',
            'indicado_em' => 'datetime',
            'primeira_assinatura_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'comissao_paga_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento: Afiliado responsável pela indicação
     */
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class, 'afiliado_id');
    }

    /**
     * Calcula o tempo de retenção (dias desde a indicação)
     */
    public function getTempoRetencaoDiasAttribute(): int
    {
        if (!$this->indicado_em) {
            return 0;
        }

        return $this->indicado_em->diffInDays(now());
    }

    /**
     * Calcula o tempo de retenção formatado
     */
    public function getTempoRetencaoFormatadoAttribute(): string
    {
        $dias = $this->tempo_retencao_dias;
        
        if ($dias < 30) {
            return "{$dias} dias";
        }
        
        $meses = floor($dias / 30);
        $diasRestantes = $dias % 30;
        
        if ($meses < 12) {
            return $diasRestantes > 0 
                ? "{$meses} mês(es) e {$diasRestantes} dias"
                : "{$meses} mês(es)";
        }
        
        $anos = floor($meses / 12);
        $mesesRestantes = $meses % 12;
        
        return $mesesRestantes > 0
            ? "{$anos} ano(s) e {$mesesRestantes} mês(es)"
            : "{$anos} ano(s)";
    }

    /**
     * Retorna o label do status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ativa' => 'Em dia',
            'inadimplente' => 'Inadimplente',
            'cancelada' => 'Cancelada',
            'trial' => 'Trial',
            default => $this->status,
        };
    }

    /**
     * Retorna a cor do status para UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'ativa' => 'success',
            'inadimplente' => 'warning',
            'cancelada' => 'error',
            'trial' => 'info',
            default => 'default',
        };
    }

    /**
     * Verifica se a comissão pode ser paga
     */
    public function podeReceberComissao(): bool
    {
        return !$this->comissao_paga 
            && $this->primeira_assinatura_em !== null
            && $this->status === 'ativa';
    }

    /**
     * Marca comissão como paga
     */
    public function marcarComissaoPaga(): void
    {
        $this->update([
            'comissao_paga' => true,
            'comissao_paga_em' => now(),
        ]);
    }

    /**
     * Scope: Por status
     */
    public function scopePorStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Por período
     */
    public function scopePorPeriodo($query, ?string $dataInicio, ?string $dataFim)
    {
        if ($dataInicio) {
            $query->where('indicado_em', '>=', $dataInicio);
        }
        
        if ($dataFim) {
            $query->where('indicado_em', '<=', $dataFim . ' 23:59:59');
        }
        
        return $query;
    }

    /**
     * Scope: Comissões pendentes
     */
    public function scopeComissoesPendentes($query)
    {
        return $query->where('comissao_paga', false)
                     ->whereNotNull('primeira_assinatura_em');
    }
}

