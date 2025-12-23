<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Orgao extends Model
{
    use SoftDeletes, HasEmpresaScope, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    public $timestamps = true;

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
        return array_merge($this->getTimestampsCasts(), [
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }
}
