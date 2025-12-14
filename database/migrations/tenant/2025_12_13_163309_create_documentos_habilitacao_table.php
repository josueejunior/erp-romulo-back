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
        Schema::create('documentos_habilitacao', function (Blueprint $table) {
            $table->id();
            // empresa_id removido - cada tenant tem seu próprio banco
            $table->string('tipo');
            $table->string('numero')->nullable();
            $table->string('identificacao')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('arquivo')->nullable();
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
        Schema::dropIfExists('documentos_habilitacao');
    }
};
