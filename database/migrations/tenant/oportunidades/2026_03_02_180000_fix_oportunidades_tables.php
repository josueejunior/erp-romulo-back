<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'oportunidades';

    /**
     * Garante que as tabelas de oportunidades existam no banco do tenant.
     */
    public function up(): void
    {
        // Criar tabela oportunidades se não existir
        if (!Schema::hasTable('oportunidades')) {
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

        // Criar tabela oportunidade_itens se não existir
        if (!Schema::hasTable('oportunidade_itens')) {
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
    }

    public function down(): void
    {
        // Não removemos nada aqui para evitar perder dados existentes.
    }
};

