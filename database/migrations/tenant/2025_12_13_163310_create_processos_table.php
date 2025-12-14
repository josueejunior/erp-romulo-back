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
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            // empresa_id removido - cada tenant tem seu próprio banco
            $table->foreignId('orgao_id')->constrained('orgaos')->onDelete('restrict');
            $table->foreignId('setor_id')->constrained('setors')->onDelete('restrict');
            $table->enum('modalidade', ['dispensa', 'pregao']);
            $table->string('numero_modalidade');
            $table->string('numero_processo_administrativo')->nullable();
            $table->boolean('srp')->default(false);
            $table->text('objeto_resumido');
            $table->dateTime('data_hora_sessao_publica');
            $table->string('endereco_entrega')->nullable();
            $table->text('forma_prazo_entrega')->nullable();
            $table->text('prazo_pagamento')->nullable();
            $table->text('validade_proposta')->nullable();
            $table->string('tipo_selecao_fornecedor')->nullable();
            $table->string('tipo_disputa')->nullable();
            $table->enum('status', [
                'participacao',
                'julgamento_habilitacao',
                'vencido',
                'perdido',
                'execucao',
                'arquivado'
            ])->default('participacao');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};
