<?php

namespace App\Modules\Documento\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;

class DocumentoHabilitacao extends BaseModel
{
    use HasFactory, HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

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

    public function processoDocumentos(): HasMany
    {
        return $this->hasMany(ProcessoDocumento::class);
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(DocumentoHabilitacaoVersao::class, 'documento_habilitacao_id');
    }
}



