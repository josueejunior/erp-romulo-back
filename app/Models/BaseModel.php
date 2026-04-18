<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as LaravelModel;
use App\Database\Schema\Blueprint;

/**
 * BaseModel
 * 
 * Classe base para todos os modelos do sistema.
 * Já define as constantes de timestamps customizados,
 * evitando que cada modelo precise declará-las.
 * 
 * O PHP 8.1+ infere automaticamente que são strings
 * porque Blueprint::CREATED_AT é const string.
 * 
 * Métodos úteis:
 * - getTableName(): string - Retorna o nome da tabela
 * - getPrimaryKey(): string - Retorna a chave primária
 * - scopeActive($query) - Scope para modelos com campo 'ativo'
 * 
 * Uso:
 * ```php
 * class MeuModel extends BaseModel
 * {
 *     // Não precisa declarar CREATED_AT, UPDATED_AT, DELETED_AT
 *     // Já estão definidas na classe base
 * }
 * ```
 */
abstract class BaseModel extends LaravelModel
{
    // Constantes de timestamps customizados
    // PHP infere automaticamente que são strings
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;

    /**
     * Retorna o nome da tabela
     */
    public function getTableName(): string
    {
        return $this->getTable();
    }

    /**
     * Retorna o nome da chave primária
     */
    public function getPrimaryKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Scope para modelos com campo 'ativo'
     * Use apenas se o modelo tiver campo 'ativo'
     */
    public function scopeActive($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Verifica se o modelo está ativo (se tiver campo 'ativo')
     */
    public function isActive(): bool
    {
        return isset($this->attributes['ativo']) ? (bool) $this->ativo : true;
    }

    /**
     * Ativa o modelo (se tiver campo 'ativo')
     */
    public function activate(): bool
    {
        if (isset($this->attributes['ativo'])) {
            $this->ativo = true;
            return $this->save();
        }
        return false;
    }

    /**
     * Desativa o modelo (se tiver campo 'ativo')
     */
    public function deactivate(): bool
    {
        if (isset($this->attributes['ativo'])) {
            $this->ativo = false;
            return $this->save();
        }
        return false;
    }
}

