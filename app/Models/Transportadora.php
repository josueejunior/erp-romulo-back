<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transportadora extends Model
{
    use HasFactory, SoftDeletes;

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
        return [
            'fornecedor_id' => 'integer',
        ];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
