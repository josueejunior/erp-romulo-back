<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

/**
 * Model para usuários administradores do sistema
 * 
 * 🔥 IMPORTANTE: Esta tabela está no banco CENTRAL, não no banco do tenant
 */
class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasTimestampsCustomizados;

    /**
     * 🔥 IMPORTANTE: Sempre usar conexão central, mesmo quando no contexto do tenant
     * Esta tabela está no banco central, não no banco do tenant
     */
    protected $connection = 'pgsql';
    
    protected $table = 'admin_users';

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ]);
    }
}

