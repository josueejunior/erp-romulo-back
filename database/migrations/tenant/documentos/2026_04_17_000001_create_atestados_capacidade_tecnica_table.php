<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'atestados_capacidade_tecnica';

    public function up(): void
    {
        Schema::create('atestados_capacidade_tecnica', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('contratante', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj_contratante', 20)->nullable();
            $table->text('objeto');
            $table->decimal('valor_contrato', 15, 2)->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->string('arquivo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atestados_capacidade_tecnica');
    }
};
