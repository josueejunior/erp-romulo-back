<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamento_itens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->onDelete('cascade');
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->decimal('custo_produto', 15, 2);
            $table->string('marca_modelo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('ajustes_especificacao');
            $table->decimal('frete', 15, 2)->default(0);
            $table->boolean('frete_incluido')->default(false);
            $table->boolean('fornecedor_escolhido')->default(false);
            $table->observacao('observacoes');
            $table->datetimes();

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
    }
};


