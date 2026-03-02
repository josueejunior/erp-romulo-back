<?php

namespace App\Modules\Oportunidade\Models;

use App\Models\TenantModel;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Oportunidade extends TenantModel
{
    use HasEmpresaScope;
    use BelongsToEmpresaTrait;

    protected $table = 'oportunidades';

    protected $fillable = [
        'empresa_id',
        'modalidade',
        'numero',
        'objeto_resumido',
        'link_oportunidade',
        'status',
    ];

    /**
     * Itens vinculados a esta oportunidade.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(OportunidadeItem::class);
    }
}

