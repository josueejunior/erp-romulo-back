<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;

class Setor extends BaseModel
{
    use HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    public $timestamps = true;

    protected $fillable = [
        'empresa_id',
        'orgao_id',
        'nome',
        'email',
        'telefone',
        'observacoes',
    ];

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class);
    }

    public function processos(): HasMany
    {
        return $this->hasMany(Processo::class);
    }
}
