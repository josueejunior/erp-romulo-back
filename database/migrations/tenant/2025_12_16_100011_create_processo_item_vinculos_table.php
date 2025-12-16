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
        Schema::create('processo_item_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('cascade');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->constrained('autorizacoes_fornecimento')->onDelete('cascade');
            $table->foreignId('empenho_id')->nullable()->constrained('empenhos')->onDelete('cascade');
            $table->decimal('quantidade', 15, 2)->default(0);
            $table->decimal('valor_unitario', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            // Um item pode ter múltiplos vínculos, mas não pode ter o mesmo vínculo duplicado
            $table->unique(['processo_item_id', 'contrato_id', 'autorizacao_fornecimento_id', 'empenho_id'], 'unique_vinculo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_item_vinculos');
    }
};


