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
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // ID do tenant (string)
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreignId('plano_id')->constrained('planos')->onDelete('restrict');
            $table->enum('status', ['ativa', 'cancelada', 'suspensa', 'expirada'])->default('ativa');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->date('data_cancelamento')->nullable();
            $table->decimal('valor_pago', 10, 2);
            $table->string('metodo_pagamento')->nullable(); // cartao, boleto, pix
            $table->string('transacao_id')->nullable(); // ID da transação no gateway
            $table->integer('dias_grace_period')->default(7); // Dias de tolerância após vencimento
            $table->text('observacoes')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('status');
            $table->index('data_fim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assinaturas');
    }
};
