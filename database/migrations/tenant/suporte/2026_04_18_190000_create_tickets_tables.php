<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->text('descricao');
            $table->text('anexo_url')->nullable();
            $table->string('status', 32)->default('aberto');
            $table->text('observacao_interna')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'empresa_id']);
            $table->index(['empresa_id', 'status']);
            $table->index('status');
        });

        Schema::create('ticket_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20)->default('user');
            $table->text('mensagem');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index('author_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_responses');
        Schema::dropIfExists('tickets');
    }
};
