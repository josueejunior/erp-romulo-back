<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class UserNotificationPreferences extends Model
{
    use HasFactory, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'email_notificacoes',
        'push_notificacoes',
        'notificar_processos_novos',
        'notificar_documentos_vencendo',
        'notificar_prazos',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'email_notificacoes' => 'boolean',
            'push_notificacoes' => 'boolean',
            'notificar_processos_novos' => 'boolean',
            'notificar_documentos_vencendo' => 'boolean',
            'notificar_prazos' => 'boolean',
        ]);
    }

    /**
     * Relacionamento com usuário
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Criar ou atualizar preferências padrão para um usuário
     */
    public static function criarOuAtualizar(int $userId, array $preferences): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            $preferences
        );
    }

    /**
     * Buscar preferências do usuário ou retornar padrões
     */
    public static function buscarOuPadrao(int $userId): array
    {
        $preferences = self::where('user_id', $userId)->first();

        if ($preferences) {
            return [
                'email_notificacoes' => $preferences->email_notificacoes,
                'push_notificacoes' => $preferences->push_notificacoes,
                'notificar_processos_novos' => $preferences->notificar_processos_novos,
                'notificar_documentos_vencendo' => $preferences->notificar_documentos_vencendo,
                'notificar_prazos' => $preferences->notificar_prazos,
            ];
        }

        // Retornar padrões
        return [
            'email_notificacoes' => true,
            'push_notificacoes' => true,
            'notificar_processos_novos' => true,
            'notificar_documentos_vencendo' => true,
            'notificar_prazos' => true,
        ];
    }
}

