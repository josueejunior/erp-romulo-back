<?php

namespace App\Services\Traits;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToEmpresa;

/**
 * Trait para verificar se um model usa empresa_id
 * Similar ao CheckClienteUsage do sistema de referência
 */
trait CheckEmpresaUsage
{
    /**
     * Verifica se o model usa empresa_id
     * Detecta através do trait BelongsToEmpresa ou método getEmpresaField()
     */
    protected function hasEmpresaUsage(string|Model $class): bool
    {
        $class = is_string($class) ? $class : get_class($class);
        
        do {
            // Verificar se tem o trait BelongsToEmpresa
            $traits = class_uses($class);
            if ($traits && in_array(BelongsToEmpresa::class, $traits)) {
                return true;
            }
            
            // Verificar também em traits recursivos
            if ($traits) {
                foreach ($traits as $trait) {
                    $traitTraits = class_uses($trait);
                    if ($traitTraits && in_array(BelongsToEmpresa::class, $traitTraits)) {
                        return true;
                    }
                }
            }
            
            // Verificar se tem método getEmpresaField()
            if (method_exists($class, 'getEmpresaField')) {
                return true;
            }
            
            // Verificar se tem empresa_id no fillable
            if (is_subclass_of($class, Model::class)) {
                $instance = new $class();
                if (in_array('empresa_id', $instance->getFillable())) {
                    return true;
                }
            }
            
            if ($class === Model::class) {
                return false;
            }
        } while ($class = get_parent_class($class));
        
        return false;
    }

    /**
     * Obtém o nome do campo de empresa do model
     */
    protected function getEmpresaField(string|Model $class): string
    {
        $class = is_string($class) ? $class : get_class($class);
        
        // Se tem método getEmpresaField(), usar ele
        if (method_exists($class, 'getEmpresaField')) {
            return $class::getEmpresaField();
        }
        
        // Padrão: empresa_id
        return 'empresa_id';
    }
}

