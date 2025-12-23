<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Empresa extends Model
{
    use SoftDeletes, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    public $timestamps = true;

    protected $fillable = [
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
        'banco_nome',
        'banco_agencia',
        'banco_conta',
        'banco_tipo',
        'banco_codigo',
        'banco_pix',
        'dados_bancarios_observacoes',
        'representante_legal',
        'logo',
        'status',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'status' => 'string',
            'emails' => 'array',
            'telefones' => 'array',
        ]);
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
