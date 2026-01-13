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
        
        // Para PostgreSQL, precisamos alterar o tipo do enum
        // Primeiro, alterar coluna para text temporariamente
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            ALTER COLUMN status TYPE text
        ");
        
        // Recriar o enum com novo valor
        DB::statement("
            DO \$\$ 
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'afiliado_comissao_status_enum') THEN
                    CREATE TYPE afiliado_comissao_status_enum AS ENUM ('pendente', 'disponivel', 'paga', 'cancelada');
                ELSE
                    -- Se enum já existe, adicionar novo valor se não existir
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'disponivel' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'afiliado_comissao_status_enum')
                    ) THEN
                        ALTER TYPE afiliado_comissao_status_enum ADD VALUE 'disponivel';
                    END IF;
                END IF;
            END \$\$;
        ");
        
        // Converter de volta para enum
        DB::statement("
            ALTER TABLE afiliado_comissoes_recorrentes 
            ALTER COLUMN status TYPE afiliado_comissao_status_enum 
            USING status::afiliado_comissao_status_enum
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

