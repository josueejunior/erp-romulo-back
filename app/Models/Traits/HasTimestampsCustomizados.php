<?php

namespace App\Models\Traits;

/**
 * Trait para modelos que usam timestamps customizados em português
 * Inclui suporte para SoftDeletes
 * 
 * NOTA: As constantes CREATED_AT, UPDATED_AT e DELETED_AT devem ser definidas
 * diretamente na classe do modelo, não no trait, para evitar conflitos.
 */
trait HasTimestampsCustomizados
{
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

