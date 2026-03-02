<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'oportunidades';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Evita erro caso a tabela já exista (ex.: criada manualmente)
        if (Schema::hasTable('oportunidades')) {
            return;
        }

        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('modalidade', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('numero', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->descricao('objeto_resumido');
            $table->string('link_oportunidade', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->enum('status', ['rascunho', 'convertida'])->default('rascunho');
            $table->datetimes();

            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};

