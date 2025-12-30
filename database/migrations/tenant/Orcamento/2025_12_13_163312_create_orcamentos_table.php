<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->onDelete('restrict');
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->string('numero_orcamento', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->date('data_orcamento')->nullable();
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->boolean('fornecedor_escolhido')->default(false);
            $table->observacao('observacoes');
            $table->datetimes();

            $table->index(['processo_id', 'fornecedor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};





