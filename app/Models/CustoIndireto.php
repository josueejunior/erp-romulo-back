<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustoIndireto extends Model
{
    use SoftDeletes;

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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}




