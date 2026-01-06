<?php

namespace App\Modules\Assinatura\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasTimestampsCustomizados;

class Plano extends BaseModel
{
    use HasTimestampsCustomizados;
    
    public $timestamps = true;

    protected $fillable = [
        'nome',
        'descricao',
        'preco_mensal',
        'preco_anual',
        'limite_processos',
        'restricao_diaria',
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
            'restricao_diaria' => 'boolean',
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

    /**
     * Verifica se o plano tem restrição diária (1 processo por dia)
     */
    public function temRestricaoDiaria(): bool
    {
        return $this->restricao_diaria === true;
    }
}

