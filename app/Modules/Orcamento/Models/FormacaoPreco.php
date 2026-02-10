<?php

namespace App\Modules\Orcamento\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BaseModel;
use App\Modules\Processo\Models\ProcessoItem;

class FormacaoPreco extends BaseModel
{
    protected $table = 'formacao_precos';

    protected $fillable = [
        'empresa_id', // Adicionado para permitir atribuição em massa
        'processo_item_id',
        'orcamento_id', // Mantido para compatibilidade
        'orcamento_item_id', // Novo: para formações de preço vinculadas a itens de orçamento
        'custo_produto',
        'frete',
        'percentual_impostos',
        'valor_impostos',
        'percentual_margem',
        'valor_margem',
        'preco_minimo',
        'preco_recomendado',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'custo_produto' => 'decimal:2',
            'frete' => 'decimal:2',
            'percentual_impostos' => 'decimal:2',
            'valor_impostos' => 'decimal:2',
            'percentual_margem' => 'decimal:2',
            'valor_margem' => 'decimal:2',
            'preco_minimo' => 'decimal:2',
            'preco_recomendado' => 'decimal:2',
        ];
    }

    public function processoItem(): BelongsTo
    {
        return $this->belongsTo(ProcessoItem::class);
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function orcamentoItem(): BelongsTo
    {
        return $this->belongsTo(OrcamentoItem::class);
    }

    /**
     * Calcula o valor mínimo de venda baseado na fórmula:
     * preco_minimo = (custo_produto + frete) * (1 + impostos%) / (1 - margem%)
     */
    public function calcularMinimoVenda(): float
    {
        $custo = ($this->custo_produto ?? 0) + ($this->frete ?? 0);
        $imposto = ($this->percentual_impostos ?? 0) / 100;
        $margem = ($this->percentual_margem ?? 0) / 100;
        
        $comImposto = $custo * (1 + $imposto);
        
        if ($margem >= 1) {
            return 0; // Margem inválida (>= 100%)
        }
        
        return round($comImposto / (1 - $margem), 2);
    }

    /**
     * Atualiza automaticamente o valor mínimo quando salva
     */
    public static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            // 🔥 CORREÇÃO: Só recalcular preco_minimo se não foi fornecido explicitamente
            // Se preco_minimo já tem um valor (não null), usar esse valor
            // Caso contrário, recalcular usando a fórmula
            if ($model->preco_minimo === null || $model->preco_minimo === 0) {
                $model->preco_minimo = $model->calcularMinimoVenda();
            }
            if (!$model->preco_recomendado) {
                $model->preco_recomendado = $model->preco_minimo * 1.1; // 10% acima do mínimo
            }
        });
    }
}



