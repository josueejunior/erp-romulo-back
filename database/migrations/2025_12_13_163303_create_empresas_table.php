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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('cnpj', 18)->unique();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('banco_nome')->nullable();
            $table->string('banco_agencia')->nullable();
            $table->string('banco_conta')->nullable();
            $table->string('banco_tipo')->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['ativa', 'inativa'])->default('ativa');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
