<?php

namespace App\Modules\Suporte\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    protected $fillable = [
        'numero',
        'user_id',
        'empresa_id',
        'descricao',
        'anexo_url',
        'status',
        'observacao_interna',
    ];

    protected $appends = [
        'anexo_view_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    public function getAnexoViewUrlAttribute(): ?string
    {
        return $this->anexo_url;
    }

    public static function numeroFromId(int $id): string
    {
        return 'TCK-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
