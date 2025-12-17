<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Adicionar campos para vincular notas fiscais a Contrato e Autorização de Fornecimento
            $table->foreignId('contrato_id')->nullable()->after('empenho_id')->constrained('contratos')->onDelete('set null');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->after('contrato_id')->constrained('autorizacoes_fornecimento')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
            $table->dropForeign(['autorizacao_fornecimento_id']);
            $table->dropColumn(['contrato_id', 'autorizacao_fornecimento_id']);
        });
    }
};
