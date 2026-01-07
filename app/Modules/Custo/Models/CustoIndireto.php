<?php

namespace App\Modules\Custo\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;

class CustoIndireto extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

    protected $table = 'custo_indiretos';

    protected $fillable = [
        'empresa_id',
        'descricao',
        'data',
        'valor',
        'categoria',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
        ];
    }
}



