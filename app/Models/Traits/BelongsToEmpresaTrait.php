<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Empresa;

/**
 * Trait BelongsToEmpresaTrait
 * 
 * Fornece o relacionamento empresa() para modelos que pertencem a uma empresa.
 * Evita repetição de código em múltiplos modelos.
 * 
 * Uso:
 * ```php
 * class MeuModel extends BaseModel
 * {
 *     use BelongsToEmpresaTrait;
 *     // Agora tem empresa() disponível automaticamente
 * }
 * ```
 */
trait BelongsToEmpresaTrait
{
    /**
     * Relacionamento com Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}



