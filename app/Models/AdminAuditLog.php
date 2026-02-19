<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log de ações administrativas (painel admin).
 *
 * Fica sempre no banco central (pgsql).
 */
class AdminAuditLog extends Model
{
    /**
     * Sempre usar conexão central.
     */
    protected $connection = 'pgsql';

    protected $table = 'admin_audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context'    => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

