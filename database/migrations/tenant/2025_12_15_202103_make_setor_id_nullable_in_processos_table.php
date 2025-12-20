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
        Schema::table('processos', function (Blueprint $table) {
            // Remover a constraint foreign key primeiro
            $table->dropForeign(['setor_id']);
            // Alterar a coluna para nullable
            $table->unsignedBigInteger('setor_id')->nullable()->change();
            // Recriar a constraint foreign key
            $table->foreign('setor_id')->references('id')->on('setors')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            // Remover a constraint foreign key
            $table->dropForeign(['setor_id']);
            // Alterar a coluna para NOT NULL (pode falhar se houver registros NULL)
            $table->unsignedBigInteger('setor_id')->nullable(false)->change();
            // Recriar a constraint foreign key
            $table->foreign('setor_id')->references('id')->on('setors')->onDelete('restrict');
        });
    }
};
