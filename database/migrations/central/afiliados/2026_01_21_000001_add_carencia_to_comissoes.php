<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        });
        
        // Para PostgreSQL, precisamos adicionar o novo valor ao enum existente
        // O Laravel cria o enum com nome baseado na tabela e coluna
        // Vamos buscar o nome do enum e adicionar o novo valor
        DB::statement("
            DO \$\$ 
            DECLARE
                enum_type_name text;
            BEGIN
                -- Buscar o nome do tipo enum usado pela coluna status
                SELECT t.typname INTO enum_type_name
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'afiliado_comissoes_recorrentes'
                  AND a.attname = 'status'
                  AND t.typtype = 'e'
                LIMIT 1;
                
                -- Se encontrou o enum, adicionar novo valor se não existir
                IF enum_type_name IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum e
                        JOIN pg_type t ON t.oid = e.enumtypid
                        WHERE t.typname = enum_type_name
                          AND e.enumlabel = 'disponivel'
                    ) THEN
                        -- PostgreSQL não suporta IF NOT EXISTS no ADD VALUE, mas já verificamos acima
                        EXECUTE format('ALTER TYPE %I ADD VALUE ''disponivel''', enum_type_name);
                    END IF;
                END IF;
            END \$\$;
        ");
        
        // Atualizar valores existentes que já passaram da carência
        DB::statement("
            UPDATE afiliado_comissoes_recorrentes 
            SET status = 'disponivel',
                data_disponivel_em = data_pagamento_cliente + INTERVAL '15 days'
            WHERE status = 'pendente' 
            AND data_pagamento_cliente IS NOT NULL 
            AND data_pagamento_cliente <= CURRENT_DATE - INTERVAL '15 days'
        ");
    }

    public function down(): void
    {
        // Reverter valores 'disponivel' para 'pendente' antes de remover o enum
        DB::statement("
            UPDATE afiliado_comissoes_recorrentes 
            SET status = 'pendente' 
            WHERE status = 'disponivel'
        ");
        
        Schema::table('afiliado_comissoes_recorrentes', function (Blueprint $table) {
            $table->dropColumn(['data_disponivel_em', 'dias_carencia']);
        });
        
        // Reverter enum (remover valor 'disponivel')
        // Nota: PostgreSQL não permite remover valores de enum diretamente
        // Seria necessário recriar o enum, mas isso é complexo e pode quebrar dados
        // Por segurança, deixamos o enum como está no rollback
    }
};

