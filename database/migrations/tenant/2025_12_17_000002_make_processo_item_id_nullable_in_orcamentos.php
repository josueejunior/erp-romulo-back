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
        Schema::table('orcamentos', function (Blueprint $table) {
            // Remover a foreign key constraint primeiro
            $table->dropForeign(['processo_item_id']);
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            // Alterar a coluna para nullable
            $table->foreignId('processo_item_id')->nullable()->change();
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            // Recriar a foreign key constraint
            $table->foreign('processo_item_id')
                ->references('id')
                ->on('processo_itens')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não podemos reverter isso com segurança se houver orçamentos sem processo_item_id
        // Mas podemos tentar se necessário
        Schema::table('orcamentos', function (Blueprint $table) {
            // Remover a foreign key constraint primeiro
            $table->dropForeign(['processo_item_id']);
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            // Alterar a coluna para NOT NULL (apenas se não houver registros null)
            $table->foreignId('processo_item_id')->nullable(false)->change();
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            // Recriar a foreign key constraint
            $table->foreign('processo_item_id')
                ->references('id')
                ->on('processo_itens')
                ->onDelete('cascade');
        });
    }
};
