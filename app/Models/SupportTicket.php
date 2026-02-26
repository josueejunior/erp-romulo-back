<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'empresa_id',
        'numero',
        'descricao',
        'anexo_url',
        'status',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'user_id');
    }

    /**
     * Tenant (empresa) no banco central. Usado na listagem admin "todas as empresas".
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SupportTicketResponse::class, 'support_ticket_id')->orderBy('created_at');
    }

    /**
     * Gera o próximo número de ticket (sequencial por ano: TKT-2026-00001).
     */
    public static function gerarNumero(): string
    {
        $ano = date('Y');
        $ultimo = static::where('numero', 'like', "TKT-{$ano}-%")
            ->orderBy('id', 'desc')
            ->value('numero');

        if (!$ultimo) {
            $seq = 1;
        } else {
            $partes = explode('-', $ultimo);
            $seq = (isset($partes[2]) ? (int) $partes[2] : 0) + 1;
        }

        return sprintf('TKT-%s-%05d', $ano, $seq);
    }
}
