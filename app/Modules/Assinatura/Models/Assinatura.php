<?php

namespace App\Modules\Assinatura\Models;

use App\Models\BaseModel;
use Carbon\Carbon;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Tenant;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class Assinatura extends BaseModel
{
    use HasTimestampsCustomizados, HasEmpresaScope, BelongsToEmpresaTrait;

    public $timestamps = true;

    /**
     * Assinaturas são dados centrais (billing), sempre ficam no banco central
     * mesmo quando o contexto de tenancy está ativo
     */
    public function getConnectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    protected $fillable = [
        'user_id', // Mantido para compatibilidade
        'tenant_id', // Mantido para compatibilidade
        'empresa_id', // 🔥 NOVO: Assinatura pertence à empresa
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
        // 🔥 MELHORIA: External Vaulting - IDs do Mercado Pago (não são dados sensíveis)
        'mercado_pago_customer_id',
        'mercado_pago_card_id',
        'mercado_pago_subscription_id',
        'ultima_tentativa_cobranca',
        'tentativas_cobranca',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'data_cancelamento' => 'date',
            'valor_pago' => 'decimal:2',
            'dias_grace_period' => 'integer',
            'ultima_tentativa_cobranca' => 'datetime',
            'tentativas_cobranca' => 'integer',
        ]);
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
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
        $statusOk = $this->status === 'ativa';
        $naoExpirada = !$this->isExpirada();
        $isAtiva = $statusOk && $naoExpirada;
        
        \Log::debug('Assinatura::isAtiva() - Verificação', [
            'assinatura_id' => $this->id,
            'status' => $this->status,
            'status_ok' => $statusOk,
            'data_fim' => $this->data_fim?->format('Y-m-d'),
            'dias_grace_period' => $this->dias_grace_period,
            'nao_expirada' => $naoExpirada,
            'is_ativa' => $isAtiva,
        ]);
        
        return $isAtiva;
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

    /**
     * Verifica se a assinatura tem cartão salvo (permite cobrança automática)
     * 
     * 🔥 MELHORIA: External Vaulting - Verifica se tem customer_id e card_id salvos
     */
    public function hasCardToken(): bool
    {
        return !empty($this->mercado_pago_customer_id) && !empty($this->mercado_pago_card_id);
    }

    /**
     * Verifica se pode tentar cobrança automática novamente
     * (evita tentativas excessivas)
     */
    public function podeTentarCobranca(): bool
    {
        // Máximo de 3 tentativas
        if ($this->tentativas_cobranca >= 3) {
            return false;
        }

        // Se nunca tentou, pode tentar
        if (!$this->ultima_tentativa_cobranca) {
            return true;
        }

        // Aguardar pelo menos 24 horas entre tentativas
        $ultimaTentativa = Carbon::parse($this->ultima_tentativa_cobranca);
        $hoje = Carbon::now();
        
        return $hoje->diffInHours($ultimaTentativa) >= 24;
    }
}

