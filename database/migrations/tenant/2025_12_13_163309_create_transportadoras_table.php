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
        Schema::create('transportadoras', function (Blueprint $table) {
            $table->id();
            // empresa_id removido - cada tenant tem seu próprio banco
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->onDelete('set null');
            $table->string('razao_social');
            $table->string('cnpj', 18)->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('contato')->nullable();
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
        Schema::dropIfExists('transportadoras');
    }
};
