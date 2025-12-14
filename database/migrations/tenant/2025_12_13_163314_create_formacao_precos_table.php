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
        Schema::create('formacao_precos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('orcamento_id')->constrained('orcamentos')->onDelete('cascade');
            $table->decimal('custo_produto', 15, 2);
            $table->decimal('frete', 15, 2)->default(0);
            $table->decimal('percentual_impostos', 5, 2)->default(0);
            $table->decimal('valor_impostos', 15, 2)->default(0);
            $table->decimal('percentual_margem', 5, 2)->default(0);
            $table->decimal('valor_margem', 15, 2)->default(0);
            $table->decimal('preco_minimo', 15, 2);
            $table->decimal('preco_recomendado', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formacao_precos');
    }
};
