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
            // Campo para indicar se o processo pede atestado de cap técnica
            // (já existe exige_atestado, mas vamos adicionar campo no processo também)
            // Na verdade, o campo exige_atestado já existe no item, então está ok
            // Mas vamos melhorar para ter controle por item
            $table->integer('quantidade_atestado_cap_tecnica')->nullable()->after('quantidade_minima_atestado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            $table->dropColumn('quantidade_atestado_cap_tecnica');
        });
    }
};

