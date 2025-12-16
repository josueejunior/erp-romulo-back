<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustoIndireto extends Model
{
    use SoftDeletes;

    protected $fillable = [
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


