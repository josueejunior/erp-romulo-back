<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Schema\Blueprint;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Models\Traits\HasTimestampsCustomizados;

class Oportunidade extends BaseModel
{
    use BelongsToEmpresaTrait;
    use HasEmpresaScope;
    use HasTimestampsCustomizados;

    public $timestamps = true;

    protected $table = 'oportunidades';

    protected $fillable = [
        'empresa_id',
        'modalidade',
        'numero',
        'objeto_resumido',
        'link_oportunidade',
        'itens',
        'pncp_numero_controle',
        'pncp_snapshot',
    ];

    protected function casts(): array
    {
        return [
            Blueprint::CREATED_AT => 'datetime',
            Blueprint::UPDATED_AT => 'datetime',
            'itens' => 'array',
            'pncp_snapshot' => 'array',
        ];
    }
}
