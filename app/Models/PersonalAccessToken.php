<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
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
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ]);
    }
}

