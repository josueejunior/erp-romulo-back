<?php

namespace App\Modules\Processo\Models;

use App\Models\TenantModel;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Processo\Models\Processo;
use App\Modules\Auth\Models\User;

class ProcessoNota extends TenantModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;

    protected $table = 'processo_notas';

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'usuario_id',
        'titulo',
        'texto',
        'data_referencia',
    ];

    protected function casts(): array
    {
        return [
            'data_referencia' => 'date',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

