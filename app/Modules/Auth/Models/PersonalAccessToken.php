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

    /**
     * Mapeamento de namespaces antigos para novos
     */
    protected static array $morphMap = [
        'App\Models\AdminUser' => \App\Modules\Auth\Models\AdminUser::class,
        'App\Models\User' => \App\Modules\Auth\Models\User::class,
    ];

    /**
     * Boot do modelo - registrar evento para mapear tokenable_type
     */
    protected static function boot()
    {
        parent::boot();

        // Mapear tokenable_type quando o modelo é carregado do banco
        static::retrieved(function ($token) {
            if (isset($token->attributes['tokenable_type']) && isset(self::$morphMap[$token->attributes['tokenable_type']])) {
                $token->attributes['tokenable_type'] = self::$morphMap[$token->attributes['tokenable_type']];
            }
        });
    }

    /**
     * Sobrescrever o relacionamento tokenable
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), $this->getTimestampsCasts(), [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ]);
    }
}

