<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'empresas';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj', 18)->unique();
            $table->endereco();
            $table->email();
            $table->telefone();
            $table->json('telefones')->nullable();
            $table->json('emails_adicionais')->nullable();
            $table->string('banco_nome', Blueprint::VARCHAR_SMALL)->nullable();
            $table->string('banco_agencia', Blueprint::VARCHAR_SMALL)->nullable();
            $table->string('banco_conta', Blueprint::VARCHAR_SMALL)->nullable();
            $table->string('banco_tipo', Blueprint::VARCHAR_SMALL)->nullable();
            $table->string('representante_legal', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('logo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->status(['ativa', 'inativa'], 'ativa');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
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
