<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'oportunidade_itens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Evita erro caso a tabela já exista (ex.: criada manualmente)
        if (Schema::hasTable('oportunidade_itens')) {
            return;
        }

        Schema::create('oportunidade_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('oportunidade_id')
                ->constrained('oportunidades')
                ->onDelete('cascade');
            $table->string('numero_orcamento', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->decimal('quantidade', 15, 2)->nullable();
            $table->string('unidade', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->text('especificacao')->nullable();
            $table->string('endereco_entrega', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->string('produto_atende', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('fornecedor', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('link_produto', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('link_catalogo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->decimal('custo_frete', 15, 2)->nullable();
            $table->datetimes();

            $table->index('oportunidade_id');
            $table->index(['empresa_id', 'oportunidade_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oportunidade_itens');
    }
};

