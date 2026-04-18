<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona suporte para vincular itens do processo diretamente à nota fiscal,
     * permitindo rastrear quais itens estão sendo faturados/pagos em cada NF.
     */
    public function up(): void
    {
        if (Schema::hasTable('processo_item_vinculos')) {
            Schema::table('processo_item_vinculos', function (Blueprint $table) {
                // Verificar se a coluna já existe antes de adicionar
                if (!Schema::hasColumn('processo_item_vinculos', 'nota_fiscal_id')) {
                    // Adicionar coluna nota_fiscal_id após empenho_id
                    $table->unsignedBigInteger('nota_fiscal_id')->nullable()->after('empenho_id');
                    
                    // Adicionar foreign key
                    if (Schema::hasTable('notas_fiscais')) {
                        $table->foreign('nota_fiscal_id')
                            ->references('id')
                            ->on('notas_fiscais')
                            ->onDelete('set null');
                    }
                    
                    // Adicionar índice para melhor performance
                    $table->index('nota_fiscal_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('processo_item_vinculos')) {
            Schema::table('processo_item_vinculos', function (Blueprint $table) {
                if (Schema::hasColumn('processo_item_vinculos', 'nota_fiscal_id')) {
                    // Remover foreign key primeiro
                    $table->dropForeign(['nota_fiscal_id']);
                    
                    // Remover índice
                    $table->dropIndex(['nota_fiscal_id']);
                    
                    // Remover coluna
                    $table->dropColumn('nota_fiscal_id');
                }
            });
        }
    }
};
