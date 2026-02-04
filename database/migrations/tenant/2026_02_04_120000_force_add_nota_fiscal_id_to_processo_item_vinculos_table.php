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
        if (Schema::hasTable('processo_item_vinculos')) {
            Schema::table('processo_item_vinculos', function (Blueprint $table) {
                if (!Schema::hasColumn('processo_item_vinculos', 'nota_fiscal_id')) {
                    $table->unsignedBigInteger('nota_fiscal_id')->nullable()->after('empenho_id');
                    $table->index('nota_fiscal_id');
                    
                    // Tentar adicionar FK se a tabela existir no mesmo esquema
                    // Em tenancy, as tabelas estão no mesmo esquema
                    /*
                    if (Schema::hasTable('notas_fiscais')) {
                        $table->foreign('nota_fiscal_id')
                            ->references('id')
                            ->on('notas_fiscais')
                            ->onDelete('set null');
                    }
                    */
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
                     // Remover foreign key se existir (precisa saber o nome)
                    // $table->dropForeign(['nota_fiscal_id']);
                    $table->dropColumn('nota_fiscal_id');
                }
            });
        }
    }
};
