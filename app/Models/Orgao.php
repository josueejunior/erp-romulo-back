<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orgao extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'uasg',
        'razao_social',
        'cnpj',
        'endereco',
        'cidade',
        'estado',
        'cep',
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    protected function casts(): array
    {
        return [
            'emails' => 'array',
            'telefones' => 'array',
        ];
    }
}
