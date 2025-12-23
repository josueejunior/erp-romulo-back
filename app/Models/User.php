<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    // Usar timestamps customizados em português
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';
    const DELETED_AT = 'excluido_em';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_ativa_id', // Para atualização automática quando obtém empresa do relacionamento
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'excluido_em' => 'datetime',
        ];
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







