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
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // 1. Remover empresa_id de processos
        if (Schema::hasColumn('processos', 'empresa_id')) {
            if ($isSqlite) {
                $pdo = DB::connection()->getPdo();
                
                // Limpar tabelas temporárias primeiro
                $pdo->exec('PRAGMA foreign_keys=OFF;');
                $pdo->exec('DROP TABLE IF EXISTS __temp__processos');
                $pdo->exec('DROP TABLE IF EXISTS processos_new');
                
                // Criar nova tabela sem a coluna empresa_id
                $pdo->exec("
                    CREATE TABLE processos_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        orgao_id INTEGER NOT NULL,
                        setor_id INTEGER NOT NULL,
                        modalidade TEXT NOT NULL CHECK(modalidade IN ('dispensa', 'pregao')),
                        numero_modalidade TEXT NOT NULL,
                        numero_processo_administrativo TEXT,
                        srp INTEGER NOT NULL DEFAULT 0,
                        objeto_resumido TEXT NOT NULL,
                        data_hora_sessao_publica DATETIME NOT NULL,
                        endereco_entrega TEXT,
                        forma_prazo_entrega TEXT,
                        prazo_pagamento TEXT,
                        validade_proposta TEXT,
                        tipo_selecao_fornecedor TEXT,
                        tipo_disputa TEXT,
                        status TEXT NOT NULL DEFAULT 'participacao' CHECK(status IN ('participacao', 'julgamento_habilitacao', 'vencido', 'perdido', 'execucao', 'arquivado')),
                        observacoes TEXT,
                        created_at DATETIME,
                        updated_at DATETIME,
                        deleted_at DATETIME,
                        FOREIGN KEY (orgao_id) REFERENCES orgaos(id),
                        FOREIGN KEY (setor_id) REFERENCES setors(id)
                    )
                ");
                
                // Copiar dados
                $pdo->exec("
                    INSERT INTO processos_new 
                    (id, orgao_id, setor_id, modalidade, numero_modalidade, numero_processo_administrativo,
                     srp, objeto_resumido, data_hora_sessao_publica, endereco_entrega, forma_prazo_entrega,
                     prazo_pagamento, validade_proposta, tipo_selecao_fornecedor, tipo_disputa, status,
                     observacoes, created_at, updated_at, deleted_at)
                    SELECT 
                        id, orgao_id, setor_id, modalidade, numero_modalidade, numero_processo_administrativo,
                        srp, objeto_resumido, data_hora_sessao_publica, endereco_entrega, forma_prazo_entrega,
                        prazo_pagamento, validade_proposta, tipo_selecao_fornecedor, tipo_disputa, status,
                        observacoes, created_at, updated_at, deleted_at
                    FROM processos
                ");
                
                // Remover tabela antiga e renomear
                $pdo->exec('DROP TABLE processos');
                $pdo->exec('ALTER TABLE processos_new RENAME TO processos');
                $pdo->exec('PRAGMA foreign_keys=ON;');
            } else {
                // Para outros bancos (MySQL, PostgreSQL)
                Schema::table('processos', function (Blueprint $table) {
                    // Tentar remover foreign key se existir
                    try {
                        $table->dropForeign(['empresa_id']);
                    } catch (\Exception $e) {
                        // Ignorar se não houver foreign key
                    }
                    $table->dropColumn('empresa_id');
                });
            }
        }

        // 2. Adicionar empresa_id nas tabelas que precisam
        $tabelas = [
            'orgaos',
            'setors',
            'fornecedores',
            'transportadoras',
            'documentos_habilitacao',
            'custos_indiretos'
        ];
        
        foreach ($tabelas as $tabela) {
            if (!Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('restrict');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter: adicionar empresa_id de volta em processos
        Schema::table('processos', function (Blueprint $table) {
            if (!Schema::hasColumn('processos', 'empresa_id')) {
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('restrict');
            }
        });

        // Reverter: remover empresa_id das outras tabelas
        $tabelas = [
            'orgaos',
            'setors',
            'fornecedores',
            'transportadoras',
            'documentos_habilitacao',
            'custos_indiretos'
        ];
        
        foreach ($tabelas as $tabela) {
            if (Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    try {
                        $table->dropForeign(['empresa_id']);
                    } catch (\Exception $e) {
                        // Ignorar se não houver foreign key
                    }
                    $table->dropColumn('empresa_id');
                });
            }
        }
    }
};
