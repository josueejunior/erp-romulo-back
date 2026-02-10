<?php

namespace App\Modules\Fornecedor\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Traits\BelongsToEmpresaTrait;

class Fornecedor extends TenantModel
{
    use HasFactory, HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    public $timestamps = true;

    protected $table = 'fornecedores';

    protected $fillable = [
        'empresa_id',
        'razao_social',
        'cnpj',
        'nome_fantasia',
        'cep',
        'logradouro',
        'numero',
        'bairro',
        'complemento',
        'cidade',
        'estado',
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
        return array_merge($this->getTimestampsCasts(), [
            'is_transportadora' => 'boolean',
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }

}

