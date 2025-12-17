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
            // Remover a constraint NOT NULL de custo_produto
            // Agora os custos estão na tabela orcamento_itens
            // Este campo é mantido apenas para compatibilidade com estrutura antiga
            $table->decimal('custo_produto', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não podemos reverter isso com segurança se houver orçamentos sem custo_produto
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->decimal('custo_produto', 15, 2)->nullable(false)->change();
        });
    }
};
