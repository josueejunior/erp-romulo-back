<?php

namespace App\Models\Traits;

use App\Database\Schema\Blueprint;

/**
 * Trait para modelos que usam timestamps customizados em português
 * Inclui suporte para SoftDeletes
 * 
 * IMPORTANTE: Este trait fornece apenas os casts. As constantes CREATED_AT, 
 * UPDATED_AT e DELETED_AT DEVEM ser declaradas na classe do modelo porque o 
 * Eloquent as acessa diretamente (Model::CREATED_AT). Use as constantes do 
 * Blueprint como referência:
 * 
 * const CREATED_AT = Blueprint::CREATED_AT;
 * const UPDATED_AT = Blueprint::UPDATED_AT;
 * const DELETED_AT = Blueprint::DELETED_AT;
 * public $timestamps = true;
 */
trait HasTimestampsCustomizados
{
    /**
     * Adiciona casts de timestamps customizados
     */
    protected function getTimestampsCasts(): array
    {
        return [
            Blueprint::CREATED_AT => 'datetime',
            Blueprint::UPDATED_AT => 'datetime',
            Blueprint::DELETED_AT => 'datetime',
        ];
    }
}

