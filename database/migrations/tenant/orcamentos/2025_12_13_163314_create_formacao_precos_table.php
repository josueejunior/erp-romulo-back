<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'formacao_precos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('formacao_precos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_item_id')->nullable()->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('orcamento_id')->constrained('orcamentos')->onDelete('cascade');
            $table->foreignId('orcamento_item_id')->nullable()->constrained('orcamento_itens')->onDelete('cascade');
            $table->decimal('custo_produto', 15, 2);
            $table->decimal('frete', 15, 2)->default(0);
            $table->decimal('percentual_impostos', 5, 2)->default(0);
            $table->decimal('valor_impostos', 15, 2)->default(0);
            $table->decimal('percentual_margem', 5, 2)->default(0);
            $table->decimal('valor_margem', 15, 2)->default(0);
            $table->decimal('preco_minimo', 15, 2);
            $table->decimal('preco_recomendado', 15, 2)->nullable();
            $table->observacao('observacoes');
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('processo_item_id');
            $table->index('orcamento_id');
            $table->index('orcamento_item_id');
            $table->index(['empresa_id', 'orcamento_id']);
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


