<?php

namespace App\Modules\Fornecedor\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasTimestampsCustomizados;

class Transportadora extends BaseModel
{
    use HasFactory, SoftDeletes, HasTimestampsCustomizados;
    
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

