<?php

namespace App\Models;

use App\Models\Traits\HasTimestampsCustomizados;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model para mapeamento direto empresa → tenant
 * 
 * 🔥 PERFORMANCE: Esta tabela elimina o loop de tenants.
 * Permite busca direta: TenantEmpresa::where('empresa_id', $empresaId)->first()
 * 
 * Esta tabela fica no banco CENTRAL (não no tenant).
 */
class TenantEmpresa extends Model
{
    use HasTimestampsCustomizados;
    
    protected $table = 'tenant_empresas';
    
    public $timestamps = true;
    
    protected $fillable = [
        'tenant_id',
        'empresa_id',
    ];
    
    /**
     * Relacionamento com Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    /**
     * Buscar tenant_id por empresa_id (método estático de conveniência)
     * 
     * @param int $empresaId
     * @return int|null
     */
    public static function findTenantIdByEmpresaId(int $empresaId): ?int
    {
        $mapping = self::where('empresa_id', $empresaId)->first();
        return $mapping?->tenant_id;
    }
    
    /**
     * Criar ou atualizar mapeamento
     * 
     * @param int $tenantId
     * @param int $empresaId
     * @return self
     */
    public static function createOrUpdateMapping(int $tenantId, int $empresaId): self
    {
        return self::updateOrCreate(
            ['empresa_id' => $empresaId],
            ['tenant_id' => $tenantId]
        );
    }
    
    /**
     * Remover mapeamento
     * 
     * @param int $empresaId
     * @return bool
     */
    public static function removeMapping(int $empresaId): bool
    {
        return self::where('empresa_id', $empresaId)->delete() > 0;
    }
}

