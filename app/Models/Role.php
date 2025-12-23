<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // Usar timestamps customizados em português
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';
    public $timestamps = true;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ]);
    }
}

