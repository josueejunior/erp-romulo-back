<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de rastreamento e controle de atribuição
     * 
     * - TTL (janela de expiração) para Last Click
     * - Campos de funil (cliques, leads, vendas)
     * - Data de expiração da referência
     */
    public function up(): void
    {
        Schema::table('afiliado_referencias', function (Blueprint $table) {
            // TTL e controle de atribuição
            $table->timestamp('expira_em')->nullable()->after('primeiro_acesso')
                ->comment('Data de expiração da referência (TTL - padrão 90 dias)');
            $table->boolean('atribuicao_valida')->default(true)->after('expira_em')
                ->comment('Se a atribuição ainda é válida (não expirou)');
            
            // Funil de conversão
            $table->boolean('registrado_como_clique')->default(true)->after('atribuicao_valida')
                ->comment('Registrado como clique (visita ao link)');
            $table->boolean('registrado_como_lead')->default(false)->after('registrado_como_clique')
                ->comment('Registrado como lead (cadastro gratuito/trial iniciado)');
            $table->boolean('registrado_como_venda')->default(false)->after('registrado_como_lead')
                ->comment('Registrado como venda (conversão paga)');
            
            // Timestamps do funil
            $table->timestamp('lead_registrado_em')->nullable()->after('registrado_como_venda');
            $table->timestamp('venda_registrada_em')->nullable()->after('lead_registrado_em');
            
            // Índices
            $table->index(['atribuicao_valida', 'expira_em']);
            $table->index(['registrado_como_lead', 'registrado_como_venda']);
        });
    }

    public function down(): void
    {
        Schema::table('afiliado_referencias', function (Blueprint $table) {
            $table->dropIndex(['atribuicao_valida', 'expira_em']);
            $table->dropIndex(['registrado_como_lead', 'registrado_como_venda']);
            
            $table->dropColumn([
                'expira_em',
                'atribuicao_valida',
                'registrado_como_clique',
                'registrado_como_lead',
                'registrado_como_venda',
                'lead_registrado_em',
                'venda_registrada_em',
            ]);
        });
    }
};

