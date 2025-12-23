<?php

namespace App\Models\Traits;

use App\Database\Schema\Blueprint;

/**
 * Trait StoreTimestamps
 * 
 * Fornece constantes tipadas para timestamps customizados.
 * O PHP 8.1+ infere automaticamente o tipo quando você atribui
 * uma constante tipada de outra classe.
 * 
 * IMPORTANTE: Este trait NÃO precisa que você declare tipos explicitamente.
 * O PHP infere automaticamente que são strings porque Blueprint::CREATED_AT
 * é declarado como `const string`.
 * 
 * Uso no Model:
 * ```php
 * class MeuModel extends Model
 * {
 *     use StoreTimestamps;
 *     
 *     // Não precisa declarar tipos! PHP infere automaticamente
 *     const CREATED_AT = Blueprint::CREATED_AT;
 *     const UPDATED_AT = Blueprint::UPDATED_AT;
 *     const DELETED_AT = Blueprint::DELETED_AT;
 * }
 * ```
 * 
 * Ou ainda mais simples, se você quiser usar diretamente:
 * ```php
 * class MeuModel extends Model
 * {
 *     // PHP infere automaticamente que é string
 *     const CREATED_AT = Blueprint::CREATED_AT;
 *     const UPDATED_AT = Blueprint::UPDATED_AT;
 *     const DELETED_AT = Blueprint::DELETED_AT;
 * }
 * ```
 */
trait StoreTimestamps
{
    /**
     * Método auxiliar para obter o nome da coluna de criação
     * Útil quando você precisa do nome da coluna em queries customizadas
     */
    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT ?? Blueprint::CREATED_AT;
    }

    /**
     * Método auxiliar para obter o nome da coluna de atualização
     */
    public function getUpdatedAtColumn(): string
    {
        return static::UPDATED_AT ?? Blueprint::UPDATED_AT;
    }

    /**
     * Método auxiliar para obter o nome da coluna de exclusão (soft delete)
     */
    public function getDeletedAtColumn(): string
    {
        return static::DELETED_AT ?? Blueprint::DELETED_AT;
    }
}

