<?php

namespace App\Database\Migrations\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Trait para verificação de dependências em migrations
 * 
 * Este trait fornece métodos para verificar se tabelas existem antes de criar foreign keys,
 * evitando erros de "relation does not exist" durante a execução de migrations.
 */
trait HasDependencyChecks
{
    /**
     * Verifica se uma ou mais tabelas existem
     * 
     * @param string|array $tables Nome da tabela ou array de nomes
     * @param bool $throwException Se true, lança exceção se alguma tabela não existir
     * @return bool|array Retorna true se todas existem, false se alguma não existe, ou array com tabelas faltantes
     * @throws \RuntimeException Se $throwException=true e alguma tabela não existir
     */
    protected function checkTablesExist($tables, bool $throwException = true)
    {
        $tables = is_array($tables) ? $tables : [$tables];
        $missing = [];
        
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }
        
        if (!empty($missing)) {
            $message = sprintf(
                'As seguintes tabelas devem existir antes desta migration: %s. ' .
                'Certifique-se de que as migrations dessas tabelas sejam executadas antes desta.',
                implode(', ', $missing)
            );
            
            if ($throwException) {
                throw new \RuntimeException($message);
            }
            
            return $missing;
        }
        
        return true;
    }
    
    /**
     * Cria uma foreign key de forma segura, verificando se a tabela referenciada existe
     * 
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $column Nome da coluna
     * @param string $referencedTable Tabela referenciada
     * @param string $referencedColumn Coluna referenciada (padrão: 'id')
     * @param string|null $onDelete Ação on delete (padrão: 'cascade')
     * @param bool $nullable Se a coluna é nullable
     * @return bool True se a foreign key foi criada, false se a tabela não existe
     */
    protected function safeForeign(
        $table,
        string $column,
        string $referencedTable,
        string $referencedColumn = 'id',
        ?string $onDelete = 'cascade',
        bool $nullable = false
    ): bool {
        if (!Schema::hasTable($referencedTable)) {
            Log::warning("Migration: Tabela {$referencedTable} não existe, pulando foreign key {$column}", [
                'migration' => static::class,
                'table' => $referencedTable,
                'column' => $column,
            ]);
            return false;
        }
        
        // Se a coluna não existe, criar primeiro
        if (!$table->getColumns() || !in_array($column, array_column($table->getColumns(), 'name'))) {
            if ($nullable) {
                $table->unsignedBigInteger($column)->nullable();
            } else {
                $table->unsignedBigInteger($column);
            }
        }
        
        // Adicionar foreign key
        $table->foreign($column)
            ->references($referencedColumn)
            ->on($referencedTable)
            ->onDelete($onDelete);
        
        return true;
    }
    
    /**
     * Adiciona foreign keys de forma segura após criar a tabela
     * Útil quando você precisa criar a tabela primeiro e adicionar foreign keys depois
     * 
     * @param string $tableName Nome da tabela
     * @param array $foreignKeys Array de configurações de foreign keys
     *   Exemplo: [
     *     ['column' => 'contrato_id', 'table' => 'contratos', 'nullable' => true],
     *     ['column' => 'empenho_id', 'table' => 'empenhos', 'nullable' => true],
     *   ]
     */
    protected function addSafeForeignKeys(string $tableName, array $foreignKeys): void
    {
        foreach ($foreignKeys as $fk) {
            $column = $fk['column'];
            $referencedTable = $fk['table'];
            $referencedColumn = $fk['referenced_column'] ?? 'id';
            $onDelete = $fk['on_delete'] ?? 'cascade';
            
            if (!Schema::hasTable($referencedTable)) {
                Log::warning("Migration: Tabela {$referencedTable} não existe, pulando foreign key {$column}", [
                    'migration' => static::class,
                    'table' => $tableName,
                    'column' => $column,
                ]);
                continue;
            }
            
            // Verificar se a coluna existe
            if (!Schema::hasColumn($tableName, $column)) {
                Log::warning("Migration: Coluna {$column} não existe na tabela {$tableName}", [
                    'migration' => static::class,
                    'table' => $tableName,
                    'column' => $column,
                ]);
                continue;
            }
            
            // Verificar se a foreign key já existe usando query direta
            try {
                $connection = Schema::getConnection();
                $doctrineSchema = $connection->getDoctrineSchemaManager();
                $tableForeignKeys = $doctrineSchema->listTableForeignKeys($tableName);
                
                $fkExists = false;
                foreach ($tableForeignKeys as $existingFk) {
                    if (in_array($column, $existingFk->getLocalColumns())) {
                        $fkExists = true;
                        break;
                    }
                }
                
                if ($fkExists) {
                    continue;
                }
            } catch (\Exception $e) {
                // Se não conseguir verificar, tentar criar mesmo assim
                Log::debug("Migration: Não foi possível verificar foreign keys existentes", [
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Adicionar foreign key
            try {
                Schema::table($tableName, function ($table) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                    $table->foreign($column)
                        ->references($referencedColumn)
                        ->on($referencedTable)
                        ->onDelete($onDelete);
                });
            } catch (\Exception $e) {
                Log::warning("Migration: Erro ao adicionar foreign key {$column}", [
                    'migration' => static::class,
                    'table' => $tableName,
                    'column' => $column,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

