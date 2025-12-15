<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fornecedores';

    protected $fillable = [
        'razao_social',
        'cnpj',
        'nome_fantasia',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'email',
        'telefone',
        'emails',
        'telefones',
        'contato',
        'observacoes',
        'is_transportadora',
    ];

    protected function casts(): array
    {
        return [
            'is_transportadora' => 'boolean',
            'emails' => 'array',
            'telefones' => 'array',
        ];
    }
}


