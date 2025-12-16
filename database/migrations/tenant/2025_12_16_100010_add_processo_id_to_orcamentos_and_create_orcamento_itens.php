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
        // Adicionar processo_id na tabela orcamentos (opcional, para orçamentos vinculados ao processo)
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->foreignId('processo_id')->nullable()->after('id')->constrained('processos')->onDelete('cascade');
        });

        // Criar tabela pivot para itens do orçamento
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->onDelete('cascade');
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->decimal('custo_produto', 15, 2);
            $table->string('marca_modelo')->nullable();
            $table->text('ajustes_especificacao')->nullable();
            $table->decimal('frete', 15, 2)->default(0);
            $table->boolean('frete_incluido')->default(false);
            $table->boolean('fornecedor_escolhido')->default(false);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            // Evitar duplicatas: um item só pode aparecer uma vez por orçamento
            $table->unique(['orcamento_id', 'processo_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
        
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropForeign(['processo_id']);
            $table->dropColumn('processo_id');
        });
    }
};

