<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_documentos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('documento_habilitacao_id')->constrained('documentos_habilitacao')->onDelete('cascade');
            $table->boolean('exigido')->default(true);
            $table->boolean('disponivel_envio')->default(false);
            $table->observacao('observacoes');
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('processo_id');
            $table->index(['empresa_id', 'processo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_documentos');
    }
};


