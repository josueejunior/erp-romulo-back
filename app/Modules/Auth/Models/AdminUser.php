<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasTimestampsCustomizados;

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

