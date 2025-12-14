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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->string('numero');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->decimal('saldo', 15, 2);
            $table->enum('situacao', ['vigente', 'encerrado', 'cancelado'])->default('vigente');
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
        Schema::dropIfExists('contratos');
    }
};
