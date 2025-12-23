<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;

class Orgao extends BaseModel
{
    use HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    public $timestamps = true;

    protected $fillable = [
        'empresa_id',
        'uasg',
        'razao_social',
        'cnpj',
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
        'observacoes',
    ];

    public function setors(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    public function processos(): HasMany
    {
        return $this->hasMany(Processo::class);
    }

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }
}
