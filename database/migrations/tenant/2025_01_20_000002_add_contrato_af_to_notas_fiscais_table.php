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
        // Verificar se a tabela existe antes de tentar alterá-la
        if (Schema::hasTable('notas_fiscais')) {
            Schema::table('notas_fiscais', function (Blueprint $table) {
                // Verificar se as colunas já existem antes de adicionar
                if (!Schema::hasColumn('notas_fiscais', 'contrato_id')) {
                    $table->foreignId('contrato_id')->nullable()->after('empenho_id')->constrained('contratos')->onDelete('set null');
                }
                if (!Schema::hasColumn('notas_fiscais', 'autorizacao_fornecimento_id')) {
                    $table->foreignId('autorizacao_fornecimento_id')->nullable()->after('contrato_id')->constrained('autorizacoes_fornecimento')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar se a tabela existe antes de tentar alterá-la
        if (Schema::hasTable('notas_fiscais')) {
            Schema::table('notas_fiscais', function (Blueprint $table) {
                if (Schema::hasColumn('notas_fiscais', 'contrato_id')) {
                    $table->dropForeign(['contrato_id']);
                    $table->dropColumn('contrato_id');
                }
                if (Schema::hasColumn('notas_fiscais', 'autorizacao_fornecimento_id')) {
                    $table->dropForeign(['autorizacao_fornecimento_id']);
                    $table->dropColumn('autorizacao_fornecimento_id');
                }
            });
        }
    }
};
