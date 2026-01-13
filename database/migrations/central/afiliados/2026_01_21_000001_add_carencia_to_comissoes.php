<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona período de carência e status "disponivel" para comissões
     * 
     * - Período de carência (15 dias) após pagamento confirmado
     * - Status "disponivel" após carência (antes de "paga")
     * - Data de disponibilização
     */
    public function up(): void
    {
        Schema::table('afiliado_comissoes_recorrentes', function (Blueprint $table) {
            // Período de carência
            $table->date('data_disponivel_em')->nullable()->after('data_pagamento_cliente')
                ->comment('Data em que a comissão fica disponível (após período de carência)');
            $table->integer('dias_carencia')->default(15)->after('data_disponivel_em')
                ->comment('Período de carência em dias (padrão: 15 dias)');
            
            // Atualizar enum de status para incluir "disponivel"
            // Nota: PostgreSQL não suporta MODIFY ENUM diretamente, então vamos usar raw SQL
        });
        
        // Para PostgreSQL, precisamos recriar o enum
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            DROP CONSTRAINT IF EXISTS afiliado_comissoes_recorrentes_status_check
        ");
        
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            ADD CONSTRAINT afiliado_comissoes_recorrentes_status_check 
            CHECK (status IN ('pendente', 'disponivel', 'paga', 'cancelada'))
        ");
        
        // Atualizar valores existentes
        DB::statement("
            UPDATE afiliado_comissoes_recorrentes 
            SET status = 'disponivel' 
            WHERE status = 'pendente' 
            AND data_pagamento_cliente IS NOT NULL 
            AND data_pagamento_cliente <= CURRENT_DATE - INTERVAL '15 days'
        ");
    }

    public function down(): void
    {
        Schema::table('afiliado_comissoes_recorrentes', function (Blueprint $table) {
            $table->dropColumn(['data_disponivel_em', 'dias_carencia']);
        });
        
        // Reverter enum
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            DROP CONSTRAINT IF EXISTS afiliado_comissoes_recorrentes_status_check
        ");
        
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            ADD CONSTRAINT afiliado_comissoes_recorrentes_status_check 
            CHECK (status IN ('pendente', 'paga', 'cancelada'))
        ");
    }
};

