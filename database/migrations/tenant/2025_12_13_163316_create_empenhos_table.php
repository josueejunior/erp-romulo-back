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
        Schema::create('empenhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->constrained('autorizacoes_fornecimento')->onDelete('set null');
            $table->string('numero');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->boolean('concluido')->default(false);
            $table->date('data_entrega')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empenhos');
    }
};
