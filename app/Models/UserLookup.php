<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLookup extends Model
{
    use SoftDeletes;
    
    /**
     * 🔥 IMPORTANTE: Sempre usar conexão central, mesmo quando no contexto do tenant
     * Esta tabela está no banco central, não no banco do tenant
     */
    protected $connection = 'pgsql';
    
    protected $table = 'users_lookup';
    
    public $timestamps = true;
    
    protected $fillable = [
        'email',
        'cnpj',
        'tenant_id',
        'user_id',
        'empresa_id',
        'status',
    ];
    
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}






