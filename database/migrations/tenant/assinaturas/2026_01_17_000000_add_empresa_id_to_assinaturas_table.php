<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona empresa_id na tabela assinaturas para permitir
     * que cada empresa tenha sua própria assinatura
     */
    public function up(): void
    {
        // Verificar se a tabela empresas existe antes de adicionar foreign key
        if (!Schema::hasTable('empresas')) {
            throw new \RuntimeException(
                'A tabela "empresas" deve existir antes de adicionar a foreign key empresa_id à tabela assinaturas. ' .
                'Certifique-se de que as migrations de empresas sejam executadas antes das migrations de assinaturas.'
            );
        }

        Schema::table('assinaturas', function (Blueprint $table) {
            // Adicionar empresa_id (nullable para compatibilidade com dados existentes)
            $table->foreignId('empresa_id')->nullable()->after('user_id')->constrained('empresas')->onDelete('cascade');
            
            // Índice composto para busca rápida por empresa
            $table->index(['empresa_id', 'status', 'data_fim'], 'idx_assinaturas_empresa_status_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropIndex('idx_assinaturas_empresa_status_data');
            $table->dropColumn('empresa_id');
        });
    }
};







