<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona índices importantes para melhorar performance de queries
     */
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            // Índices para filtros comuns
            if (!$this->hasIndex('processos', 'processos_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('processos', 'processos_status_index')) {
                $table->index('status');
            }
            if (!$this->hasIndex('processos', 'processos_orgao_id_index')) {
                $table->index('orgao_id');
            }
            // Índice composto para busca comum
            if (!$this->hasIndex('processos', 'processos_empresa_status_index')) {
                $table->index(['empresa_id', 'status']);
            }
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            if (!$this->hasIndex('orcamentos', 'orcamentos_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('orcamentos', 'orcamentos_processo_id_index')) {
                $table->index('processo_id');
            }
        });

        Schema::table('contratos', function (Blueprint $table) {
            if (!$this->hasIndex('contratos', 'contratos_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('contratos', 'contratos_processo_id_index')) {
                $table->index('processo_id');
            }
        });

        Schema::table('empenhos', function (Blueprint $table) {
            if (!$this->hasIndex('empenhos', 'empenhos_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('empenhos', 'empenhos_processo_id_index')) {
                $table->index('processo_id');
            }
            if (!$this->hasIndex('empenhos', 'empenhos_contrato_id_index')) {
                $table->index('contrato_id');
            }
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_processo_id_index')) {
                $table->index('processo_id');
            }
            if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_empenho_id_index')) {
                $table->index('empenho_id');
            }
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            if (!$this->hasIndex('fornecedores', 'fornecedores_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('fornecedores', 'fornecedores_cnpj_index')) {
                $table->index('cnpj');
            }
        });

        Schema::table('orgaos', function (Blueprint $table) {
            if (!$this->hasIndex('orgaos', 'orgaos_empresa_id_index')) {
                $table->index('empresa_id');
            }
        });

        Schema::table('setors', function (Blueprint $table) {
            if (!$this->hasIndex('setors', 'setors_empresa_id_index')) {
                $table->index('empresa_id');
            }
            if (!$this->hasIndex('setors', 'setors_orgao_id_index')) {
                $table->index('orgao_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['orgao_id']);
            $table->dropIndex(['empresa_id', 'status']);
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['processo_id']);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['processo_id']);
        });

        Schema::table('empenhos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['processo_id']);
            $table->dropIndex(['contrato_id']);
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['processo_id']);
            $table->dropIndex(['empenho_id']);
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['cnpj']);
        });

        Schema::table('orgaos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
        });

        Schema::table('setors', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['orgao_id']);
        });
    }

    /**
     * Verifica se um índice já existe
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};

