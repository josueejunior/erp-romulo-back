<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Verificar o tipo atual da coluna
            $columnInfo = DB::selectOne("
                SELECT 
                    t.typname as type_name,
                    a.attname as column_name,
                    pg_get_expr(adbin, adrelid) as default_value
                FROM pg_attribute a
                JOIN pg_class c ON a.attrelid = c.oid
                JOIN pg_type t ON a.atttypid = t.oid
                LEFT JOIN pg_attrdef ad ON a.attrelid = ad.adrelid AND a.attnum = ad.adnum
                WHERE c.relname = 'processos' 
                AND a.attname = 'status'
                AND a.attnum > 0
                AND NOT a.attisdropped
            ");
            
            $typeName = $columnInfo ? $columnInfo->type_name : null;
            
            // Se for varchar, text ou character varying, criar tipo ENUM e converter
            // Também verificar se o tipo não é um ENUM (não começa com processos_status)
            if (!$typeName || $typeName === 'varchar' || $typeName === 'text' || $typeName === 'character varying' || strpos($typeName, 'status') === false) {
                // Criar novo tipo ENUM
                DB::statement("
                    CREATE TYPE processos_status_new AS ENUM (
                        'participacao',
                        'julgamento_habilitacao',
                        'vencido',
                        'perdido',
                        'execucao',
                        'pagamento',
                        'encerramento',
                        'arquivado'
                    )
                ");
                
                // Remover default temporariamente
                DB::statement("ALTER TABLE processos ALTER COLUMN status DROP DEFAULT");
                
                // Alterar coluna para usar o novo tipo ENUM
                DB::statement("
                    ALTER TABLE processos 
                    ALTER COLUMN status TYPE processos_status_new 
                    USING status::text::processos_status_new
                ");
                
                // Restaurar default
                DB::statement("ALTER TABLE processos ALTER COLUMN status SET DEFAULT 'participacao'::processos_status_new");
                
                // Renomear tipo
                DB::statement("ALTER TYPE processos_status_new RENAME TO processos_status");
            } else {
                // Se já for ENUM, adicionar novos valores
                $oldTypeName = $typeName ?: 'processos_status';
                $newTypeName = $oldTypeName . '_new';
                
                // Criar novo tipo ENUM com todos os valores
                DB::statement("
                    CREATE TYPE {$newTypeName} AS ENUM (
                        'participacao',
                        'julgamento_habilitacao',
                        'vencido',
                        'perdido',
                        'execucao',
                        'pagamento',
                        'encerramento',
                        'arquivado'
                    )
                ");
                
                // Remover default temporariamente
                DB::statement("ALTER TABLE processos ALTER COLUMN status DROP DEFAULT");
                
                // Alterar coluna para usar o novo tipo
                DB::statement("
                    ALTER TABLE processos 
                    ALTER COLUMN status TYPE {$newTypeName} 
                    USING status::text::{$newTypeName}
                ");
                
                // Remover tipo antigo e renomear novo tipo
                DB::statement("DROP TYPE IF EXISTS {$oldTypeName} CASCADE");
                DB::statement("ALTER TYPE {$newTypeName} RENAME TO {$oldTypeName}");
                
                // Restaurar default
                DB::statement("ALTER TABLE processos ALTER COLUMN status SET DEFAULT 'participacao'::{$oldTypeName}");
            }
        } else {
            // MySQL/MariaDB: Usar sintaxe MODIFY COLUMN
            DB::statement("ALTER TABLE processos MODIFY COLUMN status ENUM(
                'participacao',
                'julgamento_habilitacao',
                'vencido',
                'perdido',
                'execucao',
                'pagamento',
                'encerramento',
                'arquivado'
            ) DEFAULT 'participacao'");
        }

        // Adicionar campo para status em participação
        Schema::table('processos', function (Blueprint $table) {
            $table->enum('status_participacao', [
                'normal',
                'adiado',
                'suspenso',
                'cancelado'
            ])->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover campo status_participacao
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn('status_participacao');
        });

        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Reverter para tipo ENUM original
            DB::statement("
                DO \$\$
                BEGIN
                    -- Criar tipo ENUM original se não existir
                    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'processos_status_old') THEN
                        CREATE TYPE processos_status_old AS ENUM (
                            'participacao',
                            'julgamento_habilitacao',
                            'vencido',
                            'perdido',
                            'execucao',
                            'arquivado'
                        );
                    END IF;
                END
                \$\$;
            ");
            
            // Verificar se há valores que precisam ser removidos antes de reverter
            DB::statement("
                UPDATE processos 
                SET status = 'arquivado'::processos_status 
                WHERE status::text IN ('pagamento', 'encerramento');
            ");
            
            // Alterar coluna para usar o tipo original
            DB::statement("
                ALTER TABLE processos 
                ALTER COLUMN status TYPE processos_status_old 
                USING status::text::processos_status_old;
            ");
            
            // Remover tipo novo e renomear tipo original
            DB::statement("
                DO \$\$
                BEGIN
                    DROP TYPE IF EXISTS processos_status CASCADE;
                    ALTER TYPE processos_status_old RENAME TO processos_status;
                END
                \$\$;
            ");
            
            // Restaurar default
            DB::statement("ALTER TABLE processos ALTER COLUMN status SET DEFAULT 'participacao'::processos_status");
        } else {
            // MySQL/MariaDB: Reverter enum
            DB::statement("ALTER TABLE processos MODIFY COLUMN status ENUM(
                'participacao',
                'julgamento_habilitacao',
                'vencido',
                'perdido',
                'execucao',
                'arquivado'
            ) DEFAULT 'participacao'");
        }
    }
};

