<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Assinatura extends Model
{
    protected $fillable = [
        'tenant_id',
        'plano_id',
        'status',
        'data_inicio',
        'data_fim',
        'data_cancelamento',
        'valor_pago',
        'metodo_pagamento',
        'transacao_id',
        'dias_grace_period',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'data_cancelamento' => 'date',
            'valor_pago' => 'decimal:2',
            'dias_grace_period' => 'integer',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }

    /**
     * Verifica se a assinatura está ativa
     */
    public function isAtiva(): bool
    {
        return $this->status === 'ativa' && !$this->isExpirada();
    }

    /**
     * Verifica se a assinatura está expirada
     */
    public function isExpirada(): bool
    {
        $hoje = Carbon::now();
        $dataFimComGrace = Carbon::parse($this->data_fim)->addDays($this->dias_grace_period);
        
        return $hoje->isAfter($dataFimComGrace);
    }

    /**
     * Verifica se está no período de grace (tolerância)
     */
    public function estaNoGracePeriod(): bool
    {
        $hoje = Carbon::now();
        $dataFim = Carbon::parse($this->data_fim);
        $dataFimComGrace = $dataFim->copy()->addDays($this->dias_grace_period);
        
        return $hoje->isAfter($dataFim) && $hoje->isBeforeOrEqualTo($dataFimComGrace);
    }

    /**
     * Retorna dias restantes até o vencimento
     */
    public function diasRestantes(): int
    {
        $hoje = Carbon::now();
        $dataFim = Carbon::parse($this->data_fim);
        
        if ($hoje->isAfter($dataFim)) {
            return 0;
        }
        
        return $hoje->diffInDays($dataFim);
    }

    /**
     * Cancela a assinatura
     */
    public function cancelar(): void
    {
        $this->status = 'cancelada';
        $this->data_cancelamento = Carbon::now();
        $this->save();
    }

    /**
     * Renova a assinatura
     */
    public function renovar(int $meses = 1): void
    {
        $dataFimAtual = Carbon::parse($this->data_fim);
        $this->data_fim = $dataFimAtual->addMonths($meses);
        $this->status = 'ativa';
        $this->data_cancelamento = null;
        $this->save();
    }
}
