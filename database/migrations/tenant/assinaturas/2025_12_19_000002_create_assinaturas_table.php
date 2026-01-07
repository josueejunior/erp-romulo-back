<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'assinaturas';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            // tenant_id deve ser bigInteger para corresponder ao id da tabela tenants
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->foreignId('plano_id')->constrained('planos')->onDelete('restrict');
            $table->status(['ativa', 'cancelada', 'suspensa', 'expirada'], 'ativa');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->date('data_cancelamento')->nullable();
            $table->decimal('valor_pago', 10, 2);
            $table->string('metodo_pagamento', Blueprint::VARCHAR_SMALL)->nullable(); // cartao, boleto, pix
            $table->string('transacao_id', Blueprint::VARCHAR_DEFAULT)->nullable(); // ID da transação no gateway
            $table->integer('dias_grace_period')->default(7); // Dias de tolerância após vencimento
            $table->observacao('observacoes');
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('tenant_id');
            $table->index('status');
            $table->index('data_inicio');
            $table->index('data_fim');
            $table->index(['tenant_id', 'status']);
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


