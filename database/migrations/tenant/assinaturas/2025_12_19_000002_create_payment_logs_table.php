<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'payment_logs';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            // tenant_id: referência lógica (tabela tenants fica só no banco central)
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plano_id')->nullable();
            $table->decimal('valor', 10, 2);
            $table->string('periodo', 20); // 'mensal' ou 'anual'
            $table->string('status', 50); // 'pending', 'approved', 'rejected', 'failed'
            $table->string('external_id', 255)->nullable(); // ID no gateway
            $table->string('idempotency_key', 255)->unique(); // Chave de idempotência
            $table->string('metodo_pagamento', 50)->nullable();
            $table->json('dados_requisicao')->nullable();
            $table->json('dados_resposta')->nullable();
            $table->text('erro')->nullable();
            $table->datetimes();
            
            // ⚡ Índices para performance
            $table->index('tenant_id');
            $table->index('external_id');
            // idempotency_key já tem índice único (->unique())
            $table->index('status');
            $table->index('plano_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};

