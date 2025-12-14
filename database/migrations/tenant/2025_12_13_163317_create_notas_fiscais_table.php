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
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('empenho_id')->nullable()->constrained('empenhos')->onDelete('set null');
            $table->enum('tipo', ['entrada', 'saida']);
            $table->string('numero');
            $table->string('serie')->nullable();
            $table->date('data_emissao');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->onDelete('set null');
            $table->decimal('valor', 15, 2);
            $table->string('arquivo')->nullable();
            $table->enum('situacao', ['pendente', 'paga', 'cancelada'])->default('pendente');
            $table->date('data_pagamento')->nullable();
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
        Schema::dropIfExists('notas_fiscais');
    }
};
