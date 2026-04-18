<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'planos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', Blueprint::VARCHAR_DEFAULT); // Básico, Profissional, Enterprise
            $table->observacao('descricao');
            $table->decimal('preco_mensal', 10, 2);
            $table->decimal('preco_anual', 10, 2)->nullable();
            $table->integer('limite_processos')->nullable(); // null = ilimitado
            $table->boolean('restricao_diaria')->default(true); // Restrição de 1 processo por dia
            $table->integer('limite_usuarios')->nullable(); // null = ilimitado
            $table->integer('limite_armazenamento_mb')->nullable(); // null = ilimitado
            $table->json('recursos_disponiveis')->nullable(); // Lista de funcionalidades
            $table->ativo();
            $table->integer('ordem')->default(0); // Para ordenação na listagem
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('ativo');
            $table->index('ordem');
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


