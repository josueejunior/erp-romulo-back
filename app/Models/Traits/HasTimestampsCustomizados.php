<?php

namespace App\Models\Traits;

use App\Database\Schema\Blueprint;

/**
 * Trait para modelos que usam timestamps customizados em português
 * Inclui suporte para SoftDeletes
 * 
 * IMPORTANTE: Este trait fornece apenas os casts. As constantes CREATED_AT, 
 * UPDATED_AT e DELETED_AT DEVEM ser declaradas na classe do modelo porque o 
 * Eloquent as acessa diretamente (Model::CREATED_AT). 
 * 
 * ✅ NÃO precisa declarar tipos explicitamente:
 * O PHP 8.1+ infere automaticamente que são strings porque Blueprint::CREATED_AT
 * é declarado como `const string`.
 * 
 * Uso no Model:
 * ```php
 * class MeuModel extends Model
 * {
 *     use SoftDeletes, HasTimestampsCustomizados;
 *     
 *     // PHP infere automaticamente que são strings - NÃO precisa: const string
 *     const CREATED_AT = Blueprint::CREATED_AT;
 *     const UPDATED_AT = Blueprint::UPDATED_AT;
 *     const DELETED_AT = Blueprint::DELETED_AT;
 *     public $timestamps = true;
 *     
 *     protected function casts(): array
 *     {
 *         return array_merge($this->getTimestampsCasts(), [
 *             // seus outros casts aqui
 *         ]);
 *     }
 * }
 * ```
 */
trait HasTimestampsCustomizados
{
    /**
     * Adiciona casts de timestamps customizados
     * 
     * @return array Array com os casts para criado_em, atualizado_em e excluido_em
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

