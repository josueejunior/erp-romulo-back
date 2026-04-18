<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\CentralConnection;

/**
 * Model para rastrear progresso de onboarding
 * 
 * Armazena o progresso do tutorial/onboarding do usuário
 */
class OnboardingProgress extends Model
{
    use CentralConnection;

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'session_id',
        'email',
        'onboarding_concluido',
        'etapas_concluidas',
        'checklist',
        'progresso_percentual',
        'iniciado_em',
        'concluido_em',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_concluido' => 'boolean',
            'etapas_concluidas' => 'array',
            'checklist' => 'array',
            'progresso_percentual' => 'integer',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'user_id');
    }
}







