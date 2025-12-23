<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'fornecedores';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj', 18)->nullable();
            $table->string('nome_fantasia', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->endereco();
            $table->email();
            $table->telefone();
            $table->json('telefones')->nullable();
            $table->json('emails')->nullable();
            $table->string('contato', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->boolean('is_transportadora')->default(false);
            $table->observacao('observacoes');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
