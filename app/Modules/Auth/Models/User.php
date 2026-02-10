<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Models\Empresa;
use App\Database\Schema\Blueprint;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes, HasTimestampsCustomizados;

    /**
     * 🔥 IMPORTANTE: Usar conexão do tenant dinamicamente
     * Esta tabela está no banco do tenant, não no banco central
     * 
     * @return string|null Nome da conexão ('tenant' ou null para usar padrão)
     */
    public function getConnectionName(): ?string
    {
        // Verificar se a conexão padrão já é 'tenant' (mais rápido)
        $defaultConnection = config('database.default');
        if ($defaultConnection === 'tenant') {
            return 'tenant';
        }
        
        // Se o tenancy estiver inicializado, usar conexão do tenant
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                return 'tenant';
            }
        } catch (\Exception $e) {
            // Se houver erro, continuar
        }
        
        // Fallback: retornar null para usar conexão padrão
        return null;
    }

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_ativa_id', // Para atualização automática quando obtém empresa do relacionamento
        'foto_perfil', // URL da foto de perfil do usuário
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ]);
    }

    /**
     * Global Scope para garantir isolamento de tenant
     * 
     * 🔥 SEGURANÇA: Impede que queries de usuários sejam executadas sem filtro de tenant
     * quando estiverem no banco central. Só é ativado se a conexão for a central e o
     * tenancy estiver inicializado, forçando um filtro através das empresas do tenant.
     */
    protected static function booted()
    {
        static::addGlobalScope('tenant_filter', function (Builder $builder) {
            if (tenancy()->initialized && tenancy()->tenant) {
                $databaseName = DB::connection()->getDatabaseName();
                
                // Só aplicar o filtro se estivermos no banco central (fallback)
                if (!str_starts_with($databaseName, 'tenant_')) {
                    $tenantId = tenancy()->tenant->id;
                    
                    // Buscar empresa_ids do tenant através da tabela tenant_empresas (banco central)
                    $empresaIds = \App\Models\TenantEmpresa::where('tenant_id', $tenantId)
                        ->pluck('empresa_id')
                        ->toArray();
                    
                    if (!empty($empresaIds)) {
                        // Filtrar usuários que têm relacionamento com empresas do tenant
                        $builder->whereHas('empresas', function ($q) use ($empresaIds) {
                            $q->whereIn('empresas.id', $empresaIds);
                        });
                    } else {
                        // Se não houver empresas mapeadas, não retornar nenhum usuário
                        $builder->whereRaw('1 = 0');
                    }
                }
                // Se estiver no banco tenant, não precisa de filtro adicional
                // pois o isolamento já é feito pelo banco de dados
            }
        });
    }

    /**
     * Empresas às quais o usuário pertence.
     * 
     * IMPORTANTE: Para evitar ambiguidade de colunas no PostgreSQL quando usado em eager loading,
     * não usamos select() customizado aqui. O Laravel gerencia as colunas automaticamente.
     */
    public function empresas(): BelongsToMany
    {
        $relation = $this->belongsToMany(Empresa::class, 'empresa_user')
            ->withPivot('perfil')
            ->withTimestamps();
        
        // PostgreSQL requer qualificação explícita de colunas em JOINs
        // Remover qualquer select padrão que possa causar ambiguidade
        // O Laravel adiciona automaticamente as colunas necessárias da tabela pivot
        return $relation;
    }

    /**
     * 🔥 NOVO: Assinaturas do usuário
     * A assinatura pertence ao usuário, não ao tenant
     */
    public function assinaturas()
    {
        return $this->hasMany(\App\Modules\Assinatura\Models\Assinatura::class, 'user_id');
    }

    /**
     * Assinatura atual do usuário
     */
    public function assinaturaAtual()
    {
        return $this->hasOne(\App\Modules\Assinatura\Models\Assinatura::class, 'user_id')
            ->where('status', '!=', 'cancelada')
            ->orderBy('data_fim', 'desc')
            ->orderBy('criado_em', 'desc');
    }
}

