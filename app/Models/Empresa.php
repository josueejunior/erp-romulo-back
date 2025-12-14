<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Empresa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'razao_social',
        'cnpj',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'email',
        'telefone',
        'banco_nome',
        'banco_agencia',
        'banco_conta',
        'banco_tipo',
        'representante_legal',
        'logo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function processos(): HasMany
    {
        return $this->hasMany(Processo::class);
    }

    public function fornecedores(): HasMany
    {
        return $this->hasMany(Fornecedor::class);
    }

    public function transportadoras(): HasMany
    {
        return $this->hasMany(Transportadora::class);
    }

    public function documentosHabilitacao(): HasMany
    {
        return $this->hasMany(DocumentoHabilitacao::class);
    }

    public function custosIndiretos(): HasMany
    {
        return $this->hasMany(CustoIndireto::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_user')
            ->withPivot('perfil')
            ->withTimestamps();
    }
}
