<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabela global de lookup para validação rápida de email e CNPJ
     * Resolve problema de performance O(n) para O(1) na validação de duplicidades
     */
    public function up(): void
    {
        Schema::create('users_lookup', function (Blueprint $table) {
            $table->id();
            
            // Dados para lookup
            $table->string('email', 255);
            $table->string('cnpj', 14);  // Sem formatação (apenas números)
            
            // Relacionamentos
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            
            // Status do registro
            $table->string('status', 50)->default('ativo');  // 'ativo', 'inativo', 'deleted'
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices únicos (permite mesmo email/CNPJ em tenants diferentes)
            $table->unique(['email', 'tenant_id'], 'users_lookup_email_tenant_unique');
            $table->unique(['cnpj', 'tenant_id'], 'users_lookup_cnpj_tenant_unique');
            
            // Índices para busca rápida (com filtro de deleted_at)
            $table->index('email');
            $table->index('cnpj');
            $table->index(['email', 'status']);
            $table->index(['cnpj', 'status']);
            $table->index('tenant_id');
            $table->index('status');
            
            // Foreign key opcional (garante integridade referencial)
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_lookup');
    }
};





