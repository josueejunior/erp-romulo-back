<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketResponse extends Model
{
    protected $table = 'support_ticket_responses';

    protected $fillable = [
        'support_ticket_id',
        'author_type',
        'author_id',
        'mensagem',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'author_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->author_type === 'admin';
    }
}
