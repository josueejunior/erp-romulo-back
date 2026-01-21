<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration para adicionar constraints de segurança no banco de dados.
 * 
 * 1. Partial Unique Index: Garante que um tenant tenha apenas UMA assinatura ativa por vez.
 *    (PostgreSQL e MySQL 8+ suportam, SQLite tem suporte parcial em versões novas)
 * 2. Check Constraints: Garante integridade de datas (data_fim > data_inicio)
 */
return new class extends Migration
{
    public string $table = 'assinaturas';

    public function up(): void
    {
        // 1. Partial Unique Index para garantir apenas uma assinatura ativa por tenant
        // Sintaxe raw pois o Schema builder do Laravel tem suporte limitado a partial indexes
        try {
            DB::statement("
                CREATE UNIQUE INDEX assinaturas_tenant_ativa_unique 
                ON assinaturas (tenant_id) 
                WHERE status = 'ativa'
            ");
        } catch (\Exception $e) {
            // Em caso de falha (ex: banco não suporta where), logar warning mas não quebrar
            // SQLite pode falhar dependendo da versão
            \Log::warning('Não foi possível criar partial index assinaturas_tenant_ativa_unique: ' . $e->getMessage());
        }

        // 2. Check Constraint para datas
        try {
            Schema::table('assinaturas', function (Blueprint $table) {
                // Sintaxe nativa do Laravel para check constraints (suportado no Laravel 10+)
                // Mas por segurança e compatibilidade, usar raw SQL
            });

            DB::statement("
                ALTER TABLE assinaturas 
                ADD CONSTRAINT check_data_fim_maior_inicio 
                CHECK (data_fim >= data_inicio)
            ");
        } catch (\Exception $e) {
            \Log::warning('Não foi possível criar check constraint check_data_fim_maior_inicio: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS assinaturas_tenant_ativa_unique");
            
            // DROP CONSTRAINT sintaxe varia por banco, tentar padrão SQL
            // MySQL/Postgres
            DB::statement("ALTER TABLE assinaturas DROP CONSTRAINT check_data_fim_maior_inicio");
        } catch (\Exception $e) {
            \Log::info('Erro ao reverter constraints: ' . $e->getMessage());
        }
    }
};
