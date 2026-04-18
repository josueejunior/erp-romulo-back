<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model para rastrear referências de afiliado
 * 
 * Armazena quando um lead acessa o site através de um link de afiliado
 */
class AfiliadoReferencia extends Model
{
    protected $table = 'afiliado_referencias';

    protected $fillable = [
        'afiliado_id',
        'referencia_code',
        'session_id',
        'ip_address',
        'user_agent',
        'email',
        'cnpj',
        'tenant_id',
        'cadastro_concluido',
        'cupom_aplicado',
        'primeiro_acesso',
        'cadastro_concluido_em',
        'metadata',
        // Novos campos de rastreamento
        'expira_em',
        'atribuicao_valida',
        'registrado_como_clique',
        'registrado_como_lead',
        'registrado_como_venda',
        'lead_registrado_em',
        'venda_registrada_em',
    ];

    protected function casts(): array
    {
        return [
            'cadastro_concluido' => 'boolean',
            'cupom_aplicado' => 'boolean',
            'atribuicao_valida' => 'boolean',
            'registrado_como_clique' => 'boolean',
            'registrado_como_lead' => 'boolean',
            'registrado_como_venda' => 'boolean',
            'primeiro_acesso' => 'datetime',
            'cadastro_concluido_em' => 'datetime',
            'expira_em' => 'datetime',
            'lead_registrado_em' => 'datetime',
            'venda_registrada_em' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Afiliado\Models\Afiliado::class, 'afiliado_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}





