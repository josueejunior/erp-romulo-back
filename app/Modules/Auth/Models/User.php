<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Empresa;
use App\Database\Schema\Blueprint;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_ativa_id', // Para atualização automática quando obtém empresa do relacionamento
        'foto_perfil', // URL da foto de perfil do usuário
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

    /**
     * Empresas às quais o usuário pertence.
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user')
            ->withPivot('perfil')
            ->withTimestamps();
    }
}

