<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\Concerns\HasEmpresaScope;
use App\Database\Schema\Blueprint;

class DocumentoHabilitacao extends Model
{
    use HasFactory, SoftDeletes, HasEmpresaScope;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;

    protected $table = 'documentos_habilitacao';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'numero',
        'identificacao',
        'data_emissao',
        'data_validade',
        'arquivo',
        'ativo',
        'observacoes',
    ];

    protected $appends = [
        'status_vencimento',
        'dias_para_vencer',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_validade' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function getStatusVencimentoAttribute(): string
    {
        if (!$this->data_validade) {
            return 'sem_data';
        }
        $data = Carbon::parse($this->data_validade);
        if ($data->isPast()) {
            return 'vencido';
        }
        if ($data->isBetween(Carbon::now(), Carbon::now()->addDays(30))) {
            return 'vencendo';
        }
        return 'ok';
    }

    public function getDiasParaVencerAttribute(): ?int
    {
        if (!$this->data_validade) {
            return null;
        }
        $data = Carbon::parse($this->data_validade);
        return Carbon::now()->diffInDays($data, false);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function processoDocumentos(): HasMany
    {
        return $this->hasMany(ProcessoDocumento::class);
    }
}


