<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->nullable()->constrained('processos')->onDelete('cascade');
            $table->foreignId('processo_item_id')->nullable()->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->onDelete('cascade');
            $table->foreignId('transportadora_id')->nullable()->constrained('fornecedores')->onDelete('set null'); // Transportadora é um fornecedor
            $table->decimal('custo_produto', 15, 2)->nullable(); // Mantido para compatibilidade, mas custos estão em orcamento_itens
            $table->string('marca_modelo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('ajustes_especificacao');
            $table->decimal('frete', 15, 2)->default(0);
            $table->boolean('frete_incluido')->default(false);
            $table->boolean('fornecedor_escolhido')->default(false);
            $table->observacao('observacoes');
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('processo_id');
            $table->index('processo_item_id');
            $table->index('fornecedor_id');
            $table->index('fornecedor_escolhido');
            $table->index(['empresa_id', 'processo_id']);
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

