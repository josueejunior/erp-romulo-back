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
            // Usar try-catch para evitar erros se já existirem
            try {
                if (!$this->hasIndex('processos', 'processos_empresa_id_index')) {
                    $table->index('empresa_id', 'processos_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('processos', 'processos_status_index')) {
                    $table->index('status', 'processos_status_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('processos', 'processos_orgao_id_index')) {
                    $table->index('orgao_id', 'processos_orgao_id_index');
                }
            } catch (\Exception $e) {}
            
            // Índice composto para busca comum
            try {
                if (!$this->hasIndex('processos', 'processos_empresa_status_index')) {
                    $table->index(['empresa_id', 'status'], 'processos_empresa_status_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('orcamentos', 'orcamentos_empresa_id_index')) {
                    $table->index('empresa_id', 'orcamentos_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('orcamentos', 'orcamentos_processo_id_index')) {
                    $table->index('processo_id', 'orcamentos_processo_id_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('contratos', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('contratos', 'contratos_empresa_id_index')) {
                    $table->index('empresa_id', 'contratos_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('contratos', 'contratos_processo_id_index')) {
                    $table->index('processo_id', 'contratos_processo_id_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('empenhos', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('empenhos', 'empenhos_empresa_id_index')) {
                    $table->index('empresa_id', 'empenhos_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('empenhos', 'empenhos_processo_id_index')) {
                    $table->index('processo_id', 'empenhos_processo_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('empenhos', 'empenhos_contrato_id_index')) {
                    $table->index('contrato_id', 'empenhos_contrato_id_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_empresa_id_index')) {
                    $table->index('empresa_id', 'notas_fiscais_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_processo_id_index')) {
                    $table->index('processo_id', 'notas_fiscais_processo_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('notas_fiscais', 'notas_fiscais_empenho_id_index')) {
                    $table->index('empenho_id', 'notas_fiscais_empenho_id_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('fornecedores', 'fornecedores_empresa_id_index')) {
                    $table->index('empresa_id', 'fornecedores_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('fornecedores', 'fornecedores_cnpj_index')) {
                    $table->index('cnpj', 'fornecedores_cnpj_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('orgaos', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('orgaos', 'orgaos_empresa_id_index')) {
                    $table->index('empresa_id', 'orgaos_empresa_id_index');
                }
            } catch (\Exception $e) {}
        });

        Schema::table('setors', function (Blueprint $table) {
            try {
                if (!$this->hasIndex('setors', 'setors_empresa_id_index')) {
                    $table->index('empresa_id', 'setors_empresa_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!$this->hasIndex('setors', 'setors_orgao_id_index')) {
                    $table->index('orgao_id', 'setors_orgao_id_index');
                }
            } catch (\Exception $e) {}
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
     * Compatível com PostgreSQL e MySQL
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();
            
            if ($driver === 'pgsql') {
                // PostgreSQL usa pg_indexes
                // O Laravel gera nomes de índices como: {table}_{column}_index
                // Mas também pode gerar nomes diferentes, então verificamos de forma mais flexível
                $result = $connection->select(
                    "SELECT COUNT(*) as count 
                     FROM pg_indexes 
                     WHERE schemaname = current_schema()
                     AND tablename = ? 
                     AND indexname = ?",
                    [$table, $indexName]
                );
                
                // Se não encontrou com o nome exato, verificar se existe índice nas colunas
                if ($result[0]->count == 0) {
                    // Extrair coluna do nome do índice (ex: processos_empresa_id_index -> empresa_id)
                    $columnMatch = preg_match('/(.+)_(.+)_index$/', $indexName, $matches);
                    if ($columnMatch && isset($matches[2])) {
                        $column = $matches[2];
                        // Verificar se existe algum índice na coluna
                        $result = $connection->select(
                            "SELECT COUNT(*) as count 
                             FROM pg_indexes 
                             WHERE schemaname = current_schema()
                             AND tablename = ? 
                             AND indexdef LIKE ?",
                            [$table, "%{$column}%"]
                        );
                    }
                }
            } else {
                // MySQL/MariaDB usa information_schema.statistics
                $databaseName = $connection->getDatabaseName();
                $result = $connection->select(
                    "SELECT COUNT(*) as count 
                     FROM information_schema.statistics 
                     WHERE table_schema = ? 
                     AND table_name = ? 
                     AND index_name = ?",
                    [$databaseName, $table, $indexName]
                );
            }
            
            return isset($result[0]) && $result[0]->count > 0;
        } catch (\Exception $e) {
            // Se houver erro ao verificar, assumir que não existe e tentar criar
            // O banco vai retornar erro se já existir, então usamos try-catch no up()
            return false;
        }
    }
    
};

