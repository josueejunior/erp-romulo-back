<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Modules\Processo\Models\Processo;

class Empresa extends BaseModel
{
    use SoftDeletes, HasTimestampsCustomizados;
    
    public $timestamps = true;

    protected $fillable = [
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
        'emails_adicionais',
        'telefones',
        'banco_nome',
        'banco_agencia',
        'banco_conta',
        'banco_tipo',
        'banco_codigo',
        // 'banco_pix', // Coluna não existe na migration
        'dados_bancarios_observacoes',
        'representante_legal',
        'logo',
        'status',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'status' => 'string',
            'emails_adicionais' => 'array',
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
