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
        Schema::table('processo_itens', function (Blueprint $table) {
            // Adicionar campo valor_arrematado após valor_final_sessao
            $table->decimal('valor_arrematado', 15, 2)->nullable()->after('valor_final_sessao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            $table->dropColumn('valor_arrematado');
        });
    }
};
