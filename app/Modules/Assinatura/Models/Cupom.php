<?php

namespace App\Modules\Assinatura\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cupom extends Model
{
    protected $table = 'cupons';

    protected $fillable = [
        'codigo',
        'tipo',
        'valor',
        'data_validade_inicio',
        'data_validade_fim',
        'limite_uso',
        'total_usado',
        'uso_unico_por_usuario',
        'planos_permitidos',
        'valor_minimo_compra',
        'ativo',
        'descricao',
    ];

    protected $casts = [
        'data_validade_inicio' => 'date',
        'data_validade_fim' => 'date',
        'valor' => 'decimal:2',
        'valor_minimo_compra' => 'decimal:2',
        'ativo' => 'boolean',
        'uso_unico_por_usuario' => 'boolean',
        'planos_permitidos' => 'array',
        'limite_uso' => 'integer',
        'total_usado' => 'integer',
    ];

    /**
     * Relacionamento com usos do cupom
     */
    public function usos(): HasMany
    {
        return $this->hasMany(CupomUso::class, 'cupom_id');
    }

    /**
     * Verifica se o cupom está válido
     */
    public function isValido(): bool
    {
        if (!$this->ativo) {
            return false;
        }

        $now = now();

        if ($this->data_validade_inicio && $now->lt($this->data_validade_inicio)) {
            return false;
        }

        if ($this->data_validade_fim && $now->gt($this->data_validade_fim)) {
            return false;
        }

        if ($this->limite_uso && $this->total_usado >= $this->limite_uso) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se o cupom pode ser usado por um tenant/plano
     */
    public function podeSerUsadoPor(int $tenantId, int $planoId, float $valorCompra): array
    {
        if (!$this->isValido()) {
            return ['valido' => false, 'motivo' => 'Cupom inválido ou expirado'];
        }

        // Verificar valor mínimo
        if ($this->valor_minimo_compra && $valorCompra < $this->valor_minimo_compra) {
            return [
                'valido' => false,
                'motivo' => "Valor mínimo de compra: R$ " . number_format($this->valor_minimo_compra, 2, ',', '.')
            ];
        }

        // Verificar planos permitidos
        if ($this->planos_permitidos && !in_array($planoId, $this->planos_permitidos)) {
            return ['valido' => false, 'motivo' => 'Cupom não válido para este plano'];
        }

        // Verificar se já foi usado pelo tenant
        if ($this->uso_unico_por_usuario) {
            $jaUsado = CupomUso::where('cupom_id', $this->id)
                ->where('tenant_id', $tenantId)
                ->exists();

            if ($jaUsado) {
                return ['valido' => false, 'motivo' => 'Cupom já utilizado'];
            }
        }

        return ['valido' => true];
    }

    /**
     * Calcula o desconto aplicado
     */
    public function calcularDesconto(float $valorOriginal): float
    {
        if ($this->tipo === 'percentual') {
            return round(($valorOriginal * $this->valor) / 100, 2);
        }

        // Valor fixo - não pode ser maior que o valor original
        return min($this->valor, $valorOriginal);
    }
}
