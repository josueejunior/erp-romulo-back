<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'notas_fiscais';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('empenho_id')->nullable()->constrained('empenhos')->onDelete('set null');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->constrained('autorizacoes_fornecimento')->onDelete('set null');
            $table->enum('tipo', ['entrada', 'saida']);
            $table->string('numero', Blueprint::VARCHAR_DEFAULT);
            $table->string('serie', Blueprint::VARCHAR_SMALL)->nullable();
            $table->date('data_emissao');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->onDelete('set null');
            $table->string('transportadora', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('numero_cte', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->date('data_entrega_prevista')->nullable();
            $table->date('data_entrega_realizada')->nullable();
            $table->status([
                'aguardando_envio',
                'em_transito',
                'entregue',
                'atrasada'
            ], null, 'situacao_logistica')->nullable();
            $table->decimal('valor', 15, 2);
            $table->decimal('custo_produto', 15, 2)->nullable();
            $table->decimal('custo_frete', 15, 2)->nullable();
            $table->decimal('custo_total', 15, 2)->nullable();
            $table->string('comprovante_pagamento', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('arquivo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->status(['pendente', 'paga', 'cancelada'], 'pendente', 'situacao');
            $table->date('data_pagamento')->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('processo_id');
            $table->index('empenho_id');
            $table->index('situacao');
            $table->index('data_emissao');
            $table->index('tipo');
            $table->index(['empresa_id', 'situacao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_fiscais');
    }
};


