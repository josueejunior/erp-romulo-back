<?php

namespace App\Models;

class AuditLog extends BaseModel
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'usuario_id', // Coluna criada por foreignUsuario() na migration
        'action', // 'created', 'updated', 'deleted', 'status_changed'
        'model_type', // 'App\Models\Processo', etc.
        'model_id',
        'old_values',
        'new_values',
        'changes',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changes' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    /**
     * Criar log de auditoria
     */
    public static function log($action, $model, $oldValues = null, $newValues = null, $description = null)
    {
        $changes = [];
        if ($oldValues && $newValues) {
            foreach ($newValues as $key => $value) {
                if (!isset($oldValues[$key]) || $oldValues[$key] !== $value) {
                    $changes[$key] = [
                        'old' => $oldValues[$key] ?? null,
                        'new' => $value,
                    ];
                }
            }
        }

        return self::create([
            'usuario_id' => auth()->id(), // Coluna é usuario_id, não user_id
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
        ]);
    }
}

