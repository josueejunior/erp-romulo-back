<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orgaos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orgaos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('uasg', Blueprint::VARCHAR_SMALL)->nullable();
            $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj', 18)->nullable();
            $table->endereco();
            $table->email();
            $table->telefone();
            $table->json('telefones')->nullable();
            $table->json('emails')->nullable();
            $table->observacao('observacoes');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('uasg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orgaos');
    }
};

