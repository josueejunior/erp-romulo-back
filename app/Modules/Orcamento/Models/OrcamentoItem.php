<?php

namespace App\Modules\Orcamento\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\BaseModel;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class OrcamentoItem extends BaseModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;
    
    protected $table = 'orcamento_itens';

    protected $fillable = [
        'empresa_id',
        'orcamento_id',
        'processo_item_id',
        'custo_produto',
        'marca_modelo',
        'ajustes_especificacao',
        'frete',
        'frete_incluido',
        'fornecedor_escolhido',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'custo_produto' => 'decimal:2',
            'frete' => 'decimal:2',
            'frete_incluido' => 'boolean',
            'fornecedor_escolhido' => 'boolean',
        ];
    }

    public function orcamento(): BelongsTo
    {
        // Remove global scope 'empresa' porque o orçamento pode ter empresa_id diferente
        // durante a consulta (ex: listagem através de processo)
        return $this->belongsTo(Orcamento::class)->withoutGlobalScope('empresa');
    }

    public function processoItem(): BelongsTo
    {
        return $this->belongsTo(ProcessoItem::class);
    }

    public function formacaoPreco(): HasOne
    {
        return $this->hasOne(FormacaoPreco::class, 'orcamento_item_id');
    }

    /**
     * Retorna o orçamento escolhido para este item (compatibilidade)
     */
    public function getOrcamentoEscolhidoAttribute(): ?OrcamentoItem
    {
        if ($this->fornecedor_escolhido) {
            return $this;
        }
        return null;
    }

    /**
     * Calcula o custo total (produto + frete se não incluído)
     */
    public function getCustoTotalAttribute(): float
    {
        return $this->custo_produto + ($this->frete_incluido ? 0 : $this->frete);
    }
}



