<?php

namespace App\Modules\Produto\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Traits\BelongsToEmpresaTrait;

class Produto extends TenantModel
{
    use HasFactory, HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    public $timestamps = true;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nome',
        'unidade',
        'descricao',
        'especificacao_tecnica',
        'marca_modelo_referencia',
        'categoria',
        'valor_estimado_padrao',
        'ativo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'valor_estimado_padrao' => 'decimal:2',
            'ativo' => 'boolean',
        ]);
    }

    /**
     * Scope para produtos ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope para buscar por categoria
     */
    public function scopePorCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope para buscar por código ou nome
     */
    public function scopeBuscar($query, string $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('codigo', 'like', "%{$termo}%")
              ->orWhere('descricao', 'like', "%{$termo}%")
              ->orWhere('especificacao_tecnica', 'like', "%{$termo}%");
        });
    }
}

