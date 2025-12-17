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
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome'); // Básico, Profissional, Enterprise
            $table->text('descricao')->nullable();
            $table->decimal('preco_mensal', 10, 2);
            $table->decimal('preco_anual', 10, 2)->nullable();
            $table->integer('limite_processos')->nullable(); // null = ilimitado
            $table->integer('limite_usuarios')->nullable(); // null = ilimitado
            $table->integer('limite_armazenamento_mb')->nullable(); // null = ilimitado
            $table->json('recursos_disponiveis')->nullable(); // Lista de funcionalidades
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0); // Para ordenação na listagem
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
