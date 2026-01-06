<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'transportadoras';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transportadoras', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->onDelete('set null');
            $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj', 18)->nullable();
            $table->string('nome_fantasia', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->endereco();
            $table->email();
            $table->telefone();
            $table->string('contato', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('fornecedor_id');
            $table->index('cnpj');
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

