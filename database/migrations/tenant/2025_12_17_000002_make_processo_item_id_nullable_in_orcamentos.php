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
            // Tornar processo_item_id nullable para permitir orçamentos vinculados diretamente ao processo
            // (que terão múltiplos itens na tabela orcamento_itens)
            $table->foreignId('processo_item_id')->nullable()->change();
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
            // Apenas se não houver registros com processo_item_id null
            $table->foreignId('processo_item_id')->nullable(false)->change();
        });
    }
};
