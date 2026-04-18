<?php

namespace App\Modules\Documento\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;

class AtestadoCapacidadeTecnica extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

    protected $table = 'atestados_capacidade_tecnica';

    protected $fillable = [
        'empresa_id',
        'contratante',
        'cnpj_contratante',
        'objeto',
        'valor_contrato',
        'data_inicio',
        'data_fim',
        'arquivo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim'    => 'date',
            'valor_contrato' => 'decimal:2',
        ];
    }
}
