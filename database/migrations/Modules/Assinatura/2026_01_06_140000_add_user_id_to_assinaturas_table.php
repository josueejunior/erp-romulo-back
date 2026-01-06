<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'assinaturas';

    /**
     * Run the migrations.
     * 
     * Adiciona user_id à tabela assinaturas para vincular assinaturas aos usuários
     * em vez de apenas aos tenants. Isso permite que um usuário tenha uma assinatura
     * e estenda os benefícios para múltiplas empresas/tenants.
     */
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            // Adicionar coluna user_id (nullable inicialmente para permitir migração de dados existentes)
            $table->unsignedBigInteger('user_id')->nullable()->after('tenant_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            // Adicionar índice para melhor performance
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};


