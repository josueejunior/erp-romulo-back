<?php

namespace App\Modules\Auth\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    protected function casts(): array
    {
        return array_merge(parent::casts(), $this->getTimestampsCasts(), [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ]);
    }
}

