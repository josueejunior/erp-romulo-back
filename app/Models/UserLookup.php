<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLookup extends Model
{
    use SoftDeletes;
    
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






