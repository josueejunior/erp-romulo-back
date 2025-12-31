<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('tipo')->index(); // orcamento_criado, orcamento_atualizado, etc
            $table->string('titulo');
            $table->text('mensagem');
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->onDelete('cascade');
            $table->foreignId('processo_id')->nullable()->constrained('processos')->onDelete('cascade');
            $table->boolean('lido')->default(false)->index();
            $table->timestamp('lido_em')->nullable();
            $table->json('dados_adicionais')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'empresa_id']);
            $table->index(['empresa_id', 'tipo']);
            $table->index(['criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
