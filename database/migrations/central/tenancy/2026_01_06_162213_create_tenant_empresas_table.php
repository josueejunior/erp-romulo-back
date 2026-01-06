<?php

declare(strict_types=1);

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar tabela de mapeamento direto empresa → tenant
 * 
 * 🔥 PERFORMANCE: Elimina o loop de tenants e inicializações desnecessárias.
 * Permite busca direta: TenantEmpresa::where('empresa_id', $empresaId)->first()
 * 
 * Esta tabela fica no banco CENTRAL (não no tenant).
 */
return new class extends Migration
{
    public string $table = 'tenant_empresas';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_empresas', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignTenant(false); // tenant_id -> tenants (obrigatório)
            $table->unsignedBigInteger('empresa_id'); // empresa_id (no banco do tenant)
            
            // Índices para performance
            $table->unique(['tenant_id', 'empresa_id'], 'tenant_empresa_unique');
            $table->index('empresa_id', 'tenant_empresas_empresa_id_index');
            $table->index('tenant_id', 'tenant_empresas_tenant_id_index');
            
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_empresas');
    }
};

