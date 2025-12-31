<?php

namespace App\Modules\Orgao\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;

class OrgaoResponsavel extends BaseModel
{
    use HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    public $timestamps = true;

    protected $table = 'orgao_responsaveis';

    protected $fillable = [
        'empresa_id',
        'orgao_id',
        'nome',
        'cargo',
        'emails',
        'telefones',
        'observacoes',
    ];

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class);
    }

    public function processos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Processo::class, 'orgao_responsavel_id');
    }

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }
}

