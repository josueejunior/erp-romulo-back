<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'produtos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('codigo', Blueprint::VARCHAR_DEFAULT)->nullable()->comment('Código interno do produto');
            $table->string('nome', Blueprint::VARCHAR_DEFAULT);
            $table->string('unidade', Blueprint::VARCHAR_SMALL)->default('UN');
            $table->observacao('descricao');
            $table->observacao('especificacao_tecnica');
            $table->string('marca_modelo_referencia', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('categoria', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->decimal('valor_estimado_padrao', 15, 2)->nullable()->comment('Valor estimado padrão para referência');
            $table->boolean('ativo')->default(true);
            $table->observacao('observacoes');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
            
            // ⚡ Índices para performance
            $table->index('empresa_id');
            $table->index('codigo');
            $table->index('ativo');
            $table->index('categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};

