<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remove a constraint única (cnpj, tenant_id) da tabela users_lookup
     * porque múltiplos usuários da mesma empresa (mesmo CNPJ) devem ter registros separados.
     * 
     * Mantém apenas (email, tenant_id) como constraint única, que é a chave correta
     * para identificar um usuário único.
     */
    public function up(): void
    {
        Schema::table('users_lookup', function (Blueprint $table) {
            // Remover constraint única de CNPJ
            $table->dropUnique('users_lookup_cnpj_tenant_unique');
            
            // Manter apenas como índice normal (não único) para busca rápida
            // O índice já existe na migration original, então não precisamos criar novamente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_lookup', function (Blueprint $table) {
            // Restaurar constraint única de CNPJ (se necessário reverter)
            $table->unique(['cnpj', 'tenant_id'], 'users_lookup_cnpj_tenant_unique');
        });
    }
};

