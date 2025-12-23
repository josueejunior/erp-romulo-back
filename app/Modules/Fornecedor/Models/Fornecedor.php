<?php

namespace App\Modules\Fornecedor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Empresa;

class Fornecedor extends BaseModel
{
    use HasFactory, SoftDeletes, HasEmpresaScope, HasTimestampsCustomizados;
    
    public $timestamps = true;

    protected $table = 'fornecedores';

    protected $fillable = [
        'empresa_id',
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
        return array_merge($this->getTimestampsCasts(), [
            'is_transportadora' => 'boolean',
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}

