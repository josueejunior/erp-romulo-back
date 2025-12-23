<?php

namespace App\Models\Traits;

/**
 * Trait para modelos que usam timestamps customizados em português
 * Inclui suporte para SoftDeletes
 */
trait HasTimestampsCustomizados
{
    // Usar timestamps customizados em português
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';
    const DELETED_AT = 'excluido_em';
    public $timestamps = true;

    /**
     * Adiciona casts de timestamps customizados
     */
    protected function getTimestampsCasts(): array
    {
        return [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'excluido_em' => 'datetime',
        ];
    }
}

