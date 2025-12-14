<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setor extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
