<?php

namespace App\Models\Concerns;

/**
 * Trait para models que pertencem a uma empresa
 * Similar ao BelongsToCliente do sistema de referência
 * 
 * Permite que o sistema detecte automaticamente que o model usa empresa_id
 * e aplique filtros automáticos nas queries
 */
trait BelongsToEmpresa
{
    /**
     * Retorna o nome do campo de empresa
     * Pode ser sobrescrito no model se usar outro nome
     */
    public static function getEmpresaField(): string
    {
        return 'empresa_id';
    }
}





