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
        if (Schema::hasTable('processo_itens')) {
            // Verificar se a coluna já existe
            if (!Schema::hasColumn('processo_itens', 'valor_arrematado')) {
                Schema::table('processo_itens', function (Blueprint $table) {
                    // Adicionar campo valor_arrematado após valor_final_sessao
                    $table->decimal('valor_arrematado', 15, 2)->nullable()->after('valor_final_sessao');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar se a tabela existe antes de tentar alterá-la
        if (Schema::hasTable('processo_itens')) {
            if (Schema::hasColumn('processo_itens', 'valor_arrematado')) {
                Schema::table('processo_itens', function (Blueprint $table) {
                    $table->dropColumn('valor_arrematado');
                });
            }
        }
    }
};
