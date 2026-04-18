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
            $table->date('data_proxima_entrega')->nullable()->after('lucro_liquido');
            $table->string('observacao_proxima_entrega', 255)->nullable()->after('data_proxima_entrega');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            $table->dropColumn(['data_proxima_entrega', 'observacao_proxima_entrega']);
        });
    }
};
