<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'razao_social',
            'cnpj',
            'email',
            'status',
            'endereco',
            'cidade',
            'estado',
            'cep',
            'telefones',
            'emails_adicionais',
            'banco',
            'agencia',
            'conta',
            'tipo_conta',
            'pix',
            'representante_legal_nome',
            'representante_legal_cpf',
            'representante_legal_cargo',
            'logo',
        ];
    }

    protected function casts(): array
    {
        return [
            'telefones' => 'array',
            'emails_adicionais' => 'array',
        ];
    }
}
