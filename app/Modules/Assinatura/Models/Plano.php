<?php

namespace App\Modules\Assinatura\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Plano extends Model
{
    use HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    protected $fillable = [
        'nome',
        'descricao',
        'preco_mensal',
        'preco_anual',
        'limite_processos',
        'limite_usuarios',
        'limite_armazenamento_mb',
        'recursos_disponiveis',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'preco_mensal' => 'decimal:2',
            'preco_anual' => 'decimal:2',
            'limite_processos' => 'integer',
            'limite_usuarios' => 'integer',
            'limite_armazenamento_mb' => 'integer',
            'recursos_disponiveis' => 'array',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ]);
    }

    public function assinaturas()
    {
        return $this->hasMany(Assinatura::class);
    }

    /**
     * Verifica se o plano está ativo
     */
    public function isAtivo(): bool
    {
        return $this->ativo === true;
    }

    /**
     * Verifica se o plano tem limite ilimitado para processos
     */
    public function temProcessosIlimitados(): bool
    {
        return $this->limite_processos === null;
    }

    /**
     * Verifica se o plano tem limite ilimitado para usuários
     */
    public function temUsuariosIlimitados(): bool
    {
        return $this->limite_usuarios === null;
    }
}

