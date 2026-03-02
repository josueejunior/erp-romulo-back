<?php

namespace App\Modules\Oportunidade\Models;

use App\Models\TenantModel;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OportunidadeItem extends TenantModel
{
    use HasEmpresaScope;
    use BelongsToEmpresaTrait;

    protected $table = 'oportunidade_itens';

    protected $fillable = [
        'empresa_id',
        'oportunidade_id',
        'numero_orcamento',
        'quantidade',
        'unidade',
        'especificacao',
        'endereco_entrega',
        'valor_estimado',
        'produto_atende',
        'fornecedor',
        'link_produto',
        'link_catalogo',
        'custo_frete',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'custo_frete' => 'decimal:2',
        ];
    }

    /**
     * Oportunidade à qual este item pertence.
     */
    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(Oportunidade::class);
    }
}

