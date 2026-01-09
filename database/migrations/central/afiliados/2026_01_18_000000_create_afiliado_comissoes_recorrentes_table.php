<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela para comissões recorrentes de afiliados
     * 
     * Registra cada ciclo de 30 dias de comissão gerada
     */
    public function up(): void
    {
        Schema::create('afiliado_comissoes_recorrentes', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('afiliado_id')->constrained('afiliados')->onDelete('cascade');
            $table->foreignId('afiliado_indicacao_id')->constrained('afiliado_indicacoes')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id')->comment('ID do tenant da empresa');
            $table->unsignedBigInteger('empresa_id')->comment('ID da empresa no tenant');
            $table->unsignedBigInteger('assinatura_id')->comment('ID da assinatura que gerou a comissão');
            
            // Dados do ciclo
            $table->date('data_inicio_ciclo')->comment('Data de início do ciclo de 30 dias');
            $table->date('data_fim_ciclo')->comment('Data de fim do ciclo de 30 dias');
            $table->date('data_pagamento_cliente')->comment('Data em que o cliente pagou');
            
            // Valores
            $table->decimal('valor_pago_cliente', 10, 2)->comment('Valor efetivamente pago pelo cliente');
            $table->decimal('comissao_percentual', 5, 2)->comment('% de comissão do afiliado');
            $table->decimal('valor_comissao', 10, 2)->comment('Valor da comissão calculada');
            
            // Status
            $table->enum('status', [
                'pendente',      // Comissão gerada, aguardando pagamento ao afiliado
                'paga',          // Comissão paga ao afiliado
                'cancelada',     // Comissão cancelada (ex: estorno)
            ])->default('pendente');
            
            // Controle de pagamento
            $table->date('data_pagamento_afiliado')->nullable()->comment('Data em que foi pago ao afiliado');
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('afiliado_id');
            $table->index('afiliado_indicacao_id');
            $table->index('tenant_id');
            $table->index('empresa_id');
            $table->index('assinatura_id');
            $table->index('status');
            $table->index('data_inicio_ciclo');
            $table->index(['afiliado_id', 'status']);
            $table->index(['afiliado_id', 'data_inicio_ciclo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliado_comissoes_recorrentes');
    }
};

