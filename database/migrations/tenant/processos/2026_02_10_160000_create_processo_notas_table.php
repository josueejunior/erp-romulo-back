<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_notas';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->text('texto');
            $table->date('data_referencia')->nullable();
            $table->datetimes();

            // Índices para performance
            $table->index('processo_id');
            $table->index(['empresa_id', 'processo_id']);
            $table->index('data_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_notas');
    }
};

