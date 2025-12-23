<?php

namespace App\Modules\Permission\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Permission extends SpatiePermission
{
    use HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    protected function casts(): array
    {
        return array_merge(parent::casts(), $this->getTimestampsCasts());
    }
}

