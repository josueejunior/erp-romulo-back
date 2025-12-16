<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Campos para logística (notas de saída)
            $table->string('transportadora')->nullable()->after('fornecedor_id');
            $table->string('numero_cte')->nullable()->after('transportadora');
            $table->date('data_entrega_prevista')->nullable()->after('numero_cte');
            $table->date('data_entrega_realizada')->nullable()->after('data_entrega_prevista');
            $table->enum('situacao_logistica', [
                'aguardando_envio',
                'em_transito',
                'entregue',
                'atrasada'
            ])->nullable()->after('data_entrega_realizada');
            
            // Campos para custos (notas de entrada)
            $table->decimal('custo_produto', 15, 2)->nullable()->after('valor');
            $table->decimal('custo_frete', 15, 2)->nullable()->after('custo_produto');
            $table->decimal('custo_total', 15, 2)->nullable()->after('custo_frete');
            $table->string('comprovante_pagamento')->nullable()->after('custo_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn([
                'transportadora',
                'numero_cte',
                'data_entrega_prevista',
                'data_entrega_realizada',
                'situacao_logistica',
                'custo_produto',
                'custo_frete',
                'custo_total',
                'comprovante_pagamento'
            ]);
        });
    }
};


