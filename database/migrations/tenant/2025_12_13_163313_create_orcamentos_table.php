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
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->onDelete('cascade');
            $table->foreignId('transportadora_id')->nullable()->constrained('transportadoras')->onDelete('set null');
            $table->decimal('custo_produto', 15, 2);
            $table->string('marca_modelo')->nullable();
            $table->text('ajustes_especificacao')->nullable();
            $table->decimal('frete', 15, 2)->default(0);
            $table->boolean('frete_incluido')->default(false);
            $table->boolean('fornecedor_escolhido')->default(false);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};
