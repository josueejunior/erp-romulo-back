<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela para pagamentos de comissões aos afiliados
     * 
     * Registra quando comissões são pagas aos afiliados
     */
    public function up(): void
    {
        Schema::create('afiliado_pagamentos_comissoes', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento
            $table->foreignId('afiliado_id')->constrained('afiliados')->onDelete('cascade');
            
            // Período de competência
            $table->date('periodo_competencia')->comment('Mês/ano da comissão (ex: 2026-01)');
            $table->date('data_pagamento')->comment('Data em que foi pago ao afiliado');
            
            // Valores
            $table->decimal('valor_total', 10, 2)->comment('Valor total pago (soma de todas as comissões)');
            $table->integer('quantidade_comissoes')->default(0)->comment('Quantidade de comissões incluídas');
            
            // Status
            $table->enum('status', [
                'pendente',  // Aguardando pagamento
                'pago',      // Pago ao afiliado
                'cancelado', // Cancelado (ex: estorno)
            ])->default('pendente');
            
            // Dados do pagamento
            $table->string('metodo_pagamento', 50)->nullable()->comment('Método usado (pix, transferencia, etc)');
            $table->string('comprovante', 255)->nullable()->comment('URL ou referência do comprovante');
            $table->text('observacoes')->nullable();
            
            // Controle
            $table->foreignId('pago_por')->nullable()->comment('ID do admin que marcou como pago');
            $table->timestamp('pago_em')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('afiliado_id');
            $table->index('periodo_competencia');
            $table->index('status');
            $table->index(['afiliado_id', 'periodo_competencia']);
            $table->index(['afiliado_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliado_pagamentos_comissoes');
    }
};


