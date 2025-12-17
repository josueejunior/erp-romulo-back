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
        // Adicionar numero_cte em contratos
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('numero_cte')->nullable()->after('arquivo_contrato');
        });

        // Adicionar numero_cte em empenhos
        Schema::table('empenhos', function (Blueprint $table) {
            $table->string('numero_cte')->nullable()->after('observacoes');
        });

        // Adicionar numero_cte em autorizações de fornecimento
        Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
            $table->string('numero_cte')->nullable()->after('observacoes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('numero_cte');
        });

        Schema::table('empenhos', function (Blueprint $table) {
            $table->dropColumn('numero_cte');
        });

        Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
            $table->dropColumn('numero_cte');
        });
    }
};


