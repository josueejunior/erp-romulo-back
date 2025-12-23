<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasEmpresaScope;

/**
 * Trait HasSoftDeletesWithEmpresa
 * 
 * Combina SoftDeletes e HasEmpresaScope em um único trait.
 * Útil para modelos que precisam de soft deletes e filtro por empresa.
 * 
 * Uso:
 * ```php
 * class MeuModel extends BaseModel
 * {
 *     use HasSoftDeletesWithEmpresa;
 *     // Agora tem SoftDeletes + HasEmpresaScope + timestamps customizados
 * }
 * ```
 */
trait HasSoftDeletesWithEmpresa
{
    use SoftDeletes, HasEmpresaScope;
}

