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
        Schema::table('processos', function (Blueprint $table) {
            // Link do edital/portal
            $table->string('link_edital')->nullable()->after('numero_processo_administrativo');
            $table->string('portal')->nullable()->after('link_edital');
            
            // Números em edital
            $table->string('numero_edital')->nullable()->after('portal');
            
            // Locais e prazos detalhados
            $table->text('local_entrega_detalhado')->nullable()->after('endereco_entrega');
            $table->text('prazos_detalhados')->nullable()->after('prazo_pagamento');
            
            // Validade da proposta (data de início e fim)
            $table->date('validade_proposta_inicio')->nullable()->after('validade_proposta');
            $table->date('validade_proposta_fim')->nullable()->after('validade_proposta_inicio');
            
            // Data de arquivamento (quando marcado como perdido)
            $table->dateTime('data_arquivamento')->nullable()->after('observacoes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn([
                'link_edital',
                'portal',
                'numero_edital',
                'local_entrega_detalhado',
                'prazos_detalhados',
                'validade_proposta_inicio',
                'validade_proposta_fim',
                'data_arquivamento'
            ]);
        });
    }
};



