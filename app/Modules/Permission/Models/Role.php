<?php

namespace App\Modules\Permission\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Role extends SpatieRole
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

