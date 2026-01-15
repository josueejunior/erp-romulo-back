<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantConnectionInfo extends Command
{
    protected $signature = 'tenant:connection-info 
                            {tenant_id : ID do tenant}
                            {--show-password : Mostrar senha na string de conexão}';

    protected $description = 'Mostra informações de conexão para acessar o banco de dados de um tenant externamente';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $showPassword = $this->option('show-password');
        
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("❌ Tenant com ID {$tenantId} não encontrado!");
            return 1;
        }
        
        try {
            // Obter nome do banco
            $databaseName = $tenant->database()->getName();
            
            // Obter configurações do banco central
            $connectionName = config('database.default');
            $config = config("database.connections.{$connectionName}");
            
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? '5432';
            $username = $config['username'] ?? 'postgres';
            $password = $config['password'] ?? '';
            
            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔌 INFORMAÇÕES DE CONEXÃO - TENANT {$tenantId}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();
            
            $this->line("📋 <fg=cyan>Tenant:</> {$tenant->razao_social} (ID: {$tenant->id})");
            $this->line("🏢 <fg=cyan>CNPJ:</> " . ($tenant->cnpj ?? 'N/A'));
            $this->newLine();
            
            $this->info("⚙️  PARÂMETROS DE CONEXÃO:");
            $this->line("   Host: <fg=yellow>{$host}</>");
            $this->line("   Port: <fg=yellow>{$port}</>");
            $this->line("   Database: <fg=yellow>{$databaseName}</>");
            $this->line("   Username: <fg=yellow>{$username}</>");
            $this->line("   Password: <fg=" . ($showPassword ? "yellow>{$password}" : "gray>*** (use --show-password para mostrar)") . "</>");
            $this->newLine();
            
            // String de conexão psql
            $passwordPart = $showPassword ? ":$password" : "";
            $psqlCommand = "psql -h {$host} -p {$port} -U {$username} -d {$databaseName}";
            $this->info("💻 LINHA DE COMANDO (psql):");
            $this->line("   <fg=cyan>{$psqlCommand}</>");
            $this->newLine();
            
            // String de conexão PDO/DSN
            $passwordPartDsn = $showPassword ? ":$password" : "";
            $dsn = "pgsql:host={$host};port={$port};dbname={$databaseName}";
            $this->info("🔗 STRING DE CONEXÃO (DSN):");
            $this->line("   <fg=cyan>{$dsn}</>");
            $this->newLine();
            
            // URL de conexão
            if ($showPassword) {
                $url = "postgresql://{$username}:{$password}@{$host}:{$port}/{$databaseName}";
                $this->info("🌐 URL DE CONEXÃO:");
                $this->line("   <fg=cyan>{$url}</>");
                $this->newLine();
            }
            
            // Informações para ferramentas gráficas
            $this->info("🖥️  FERRAMENTAS GRÁFICAS (DBeaver, pgAdmin, etc):");
            $this->table(
                ['Parâmetro', 'Valor'],
                [
                    ['Connection Type', 'PostgreSQL'],
                    ['Host', $host],
                    ['Port', $port],
                    ['Database', $databaseName],
                    ['Username', $username],
                    ['Password', $showPassword ? $password : '***'],
                ]
            );
            $this->newLine();
            
            // Testar conexão
            if ($this->confirm('Deseja testar a conexão agora?', true)) {
                $this->testConnection($host, $port, $databaseName, $username, $password);
            }
            
            $this->newLine();
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao obter informações: " . $e->getMessage());
            return 1;
        }
    }
    
    protected function testConnection($host, $port, $database, $username, $password)
    {
        $this->info("🧪 Testando conexão...");
        
        try {
            $pdo = new \PDO(
                "pgsql:host={$host};port={$port};dbname={$database}",
                $username,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Testar query
            $stmt = $pdo->query("SELECT version() as version, current_database() as database");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $this->newLine();
            $this->info("✅ <fg=green>Conexão bem-sucedida!</fg>");
            $this->line("   PostgreSQL Version: " . substr($result['version'], 0, 50) . "...");
            $this->line("   Database: {$result['database']}");
            
            // Contar tabelas
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'public'");
            $tablesCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            $this->line("   Tabelas: {$tablesCount}");
            
        } catch (\PDOException $e) {
            $this->error("❌ Erro na conexão: " . $e->getMessage());
            return 1;
        }
    }
}
