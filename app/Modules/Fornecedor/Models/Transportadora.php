<?php

namespace App\Modules\Fornecedor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Transportadora extends Model
{
    use HasFactory, SoftDeletes, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    public $timestamps = true;

    protected $table = 'transportadoras';

    protected $fillable = [
        'fornecedor_id',
        'razao_social',
        'cnpj',
        'nome_fantasia',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'email',
        'telefone',
        'contato',
        'observacoes',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'fornecedor_id' => 'integer',
        ]);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}

