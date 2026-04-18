<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'documentos_habilitacao';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documentos_habilitacao', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('tipo', Blueprint::VARCHAR_DEFAULT);
            $table->string('numero', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('identificacao', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('arquivo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('tipo');
            $table->index('data_validade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_habilitacao');
    }
};


