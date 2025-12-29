<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class ListTenantDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list-databases 
                            {--tenants=* : IDs dos tenants específicos (opcional)}
                            {--tables : Mostrar tabelas de cada banco}
                            {--details : Mostrar detalhes das tabelas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista todos os bancos de dados dos tenants e suas tabelas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenants = $this->getTenants();
        
        if (empty($tenants)) {
            $this->warn('Nenhum tenant encontrado.');
            return 0;
        }

        $this->info("📊 Encontrados " . $tenants->count() . " tenant(s)\n");

        $showTables = $this->option('tables');
        $showDetails = $this->option('details');

        // Listar banco central primeiro
        $this->listCentralDatabase();

        // Listar bancos dos tenants
        foreach ($tenants as $tenant) {
            $this->listTenantDatabase($tenant, $showTables, $showDetails);
        }

        $this->newLine();
        $this->info('✅ Listagem concluída!');
        return 0;
    }

    /**
     * Lista informações do banco central
     */
    protected function listCentralDatabase()
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🗄️  BANCO CENTRAL");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Nome: <fg=cyan>{$databaseName}</>");
        $this->line("Conexão: <fg=cyan>{$connection->getName()}</>");
        
        if ($this->option('tables')) {
            $tables = $this->getTables($connection);
            $this->line("Tabelas: <fg=yellow>" . count($tables) . "</>");
            
            if ($this->option('details')) {
                $this->showTablesDetails($tables, $connection);
            } else {
                $this->showTablesList($tables);
            }
        }
        
        $this->newLine();
    }

    /**
     * Lista informações do banco de um tenant
     */
    protected function listTenantDatabase(Tenant $tenant, bool $showTables, bool $showDetails)
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🏢 TENANT: {$tenant->id}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("ID: <fg=cyan>{$tenant->id}</>");
        $this->line("Razão Social: <fg=cyan>" . ($tenant->razao_social ?? 'N/A') . "</>");
        $this->line("CNPJ: <fg=cyan>" . ($tenant->cnpj ?? 'N/A') . "</>");
        $this->line("Status: <fg=" . ($tenant->status === 'ativa' ? 'green' : 'red') . ">{$tenant->status}</>");

        try {
            // Obter nome do banco de dados
            $databaseName = $tenant->database()->getName();
            $this->line("Banco de Dados: <fg=cyan>{$databaseName}</>");

            if ($showTables) {
                // Inicializar contexto do tenant
                tenancy()->initialize($tenant);
                
                try {
                    $connection = DB::connection('tenant');
                    $tables = $this->getTables($connection);
                    $this->line("Tabelas: <fg=yellow>" . count($tables) . "</>");
                    
                    if ($showDetails) {
                        $this->showTablesDetails($tables, $connection);
                    } else {
                        $this->showTablesList($tables);
                    }
                } finally {
                    tenancy()->end();
                }
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Erro ao acessar banco: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Obtém lista de tabelas de uma conexão
     */
    protected function getTables($connection): array
    {
        try {
            $driver = $connection->getDriverName();
            
            if ($driver === 'pgsql') {
                $query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename";
                return $connection->select($query);
            } elseif ($driver === 'mysql') {
                $database = $connection->getDatabaseName();
                $query = "SELECT TABLE_NAME as tablename FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME";
                return $connection->select($query, [$database]);
            } else {
                return [];
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Erro ao listar tabelas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mostra lista simples de tabelas
     */
    protected function showTablesList(array $tables)
    {
        if (empty($tables)) {
            $this->line("  <fg=gray>Nenhuma tabela encontrada</>");
            return;
        }

        $tableNames = array_map(function($table) {
            return is_object($table) ? $table->tablename : $table['tablename'];
        }, $tables);

        $this->line("  " . implode(', ', $tableNames));
    }

    /**
     * Mostra detalhes das tabelas
     */
    protected function showTablesDetails(array $tables, $connection)
    {
        if (empty($tables)) {
            $this->line("  <fg=gray>Nenhuma tabela encontrada</>");
            return;
        }

        $driver = $connection->getDriverName();
        
        foreach ($tables as $table) {
            $tableName = is_object($table) ? $table->tablename : $table['tablename'];
            
            try {
                if ($driver === 'pgsql') {
                    $rowCount = $connection->table($tableName)->count();
                    $sizeQuery = "SELECT pg_size_pretty(pg_total_relation_size('{$tableName}')) as size";
                    $sizeResult = $connection->selectOne($sizeQuery);
                    $size = $sizeResult->size ?? 'N/A';
                } elseif ($driver === 'mysql') {
                    $rowCount = $connection->table($tableName)->count();
                    $sizeQuery = "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = ?";
                    $sizeResult = $connection->selectOne($sizeQuery, [$tableName]);
                    $size = ($sizeResult->size_mb ?? 0) . ' MB';
                } else {
                    $rowCount = $connection->table($tableName)->count();
                    $size = 'N/A';
                }
                
                $this->line("  📋 <fg=cyan>{$tableName}</> - <fg=yellow>{$rowCount}</> registros - <fg=gray>{$size}</>");
            } catch (\Exception $e) {
                $this->line("  📋 <fg=cyan>{$tableName}</> - <fg=red>Erro ao obter detalhes</>");
            }
        }
    }

    /**
     * Get tenants to process
     */
    protected function getTenants()
    {
        $tenantIds = $this->option('tenants');
        
        if (!empty($tenantIds)) {
            return Tenant::whereIn('id', $tenantIds)->get();
        }
        
        return Tenant::all();
    }
}




