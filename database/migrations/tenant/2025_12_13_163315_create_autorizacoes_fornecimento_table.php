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
        Schema::create('autorizacoes_fornecimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->string('numero');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->decimal('saldo', 15, 2);
            $table->enum('situacao', [
                'aguardando_empenho',
                'atendendo',
                'concluida'
            ])->default('aguardando_empenho');
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
        Schema::dropIfExists('autorizacoes_fornecimento');
    }
};
