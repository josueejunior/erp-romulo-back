<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoogleIntegration extends Model
{
    protected $table = 'user_google_integrations';

    protected $fillable = [
        'user_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'calendar_id',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

