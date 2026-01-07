<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_item_vinculos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_item_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('cascade');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->constrained('autorizacoes_fornecimento')->onDelete('cascade');
            $table->foreignId('empenho_id')->nullable()->constrained('empenhos')->onDelete('cascade');
            $table->decimal('quantidade', 15, 2)->default(0);
            $table->decimal('valor_unitario', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->observacao('observacoes');
            $table->datetimes();

            // Um item pode ter múltiplos vínculos, mas não pode ter o mesmo vínculo duplicado
            $table->unique(['processo_item_id', 'contrato_id', 'autorizacao_fornecimento_id', 'empenho_id'], 'unique_vinculo');
            
            // ⚡ Índices para performance
            $table->index('processo_item_id');
            $table->index('contrato_id');
            $table->index('autorizacao_fornecimento_id');
            $table->index('empenho_id');
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


