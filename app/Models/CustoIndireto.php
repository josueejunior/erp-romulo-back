<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasEmpresaScope;
use App\Database\Schema\Blueprint;

class CustoIndireto extends Model
{
    use SoftDeletes, HasEmpresaScope;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;

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




