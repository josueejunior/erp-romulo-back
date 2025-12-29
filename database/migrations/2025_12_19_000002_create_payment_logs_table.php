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
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->foreignId('plano_id')->nullable()->constrained('planos')->onDelete('restrict');
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
            
            // Índices
            $table->index('tenant_id');
            $table->index('external_id');
            $table->index('idempotency_key');
            $table->index('status');
            $table->index('plano_id');
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

