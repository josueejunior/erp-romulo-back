<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'setors';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('setors', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignIdCustom('orgao_id', 'orgaos', false, 'cascade');
            $table->string('nome', Blueprint::VARCHAR_DEFAULT);
            $table->email();
            $table->telefone();
            $table->observacao('observacoes');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('orgao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setors');
    }
};

