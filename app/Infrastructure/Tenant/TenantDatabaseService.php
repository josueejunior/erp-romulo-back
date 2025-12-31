<?php

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Models\Tenant as TenantModel;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do serviço de banco de dados do tenant
 * Conhece detalhes de infraestrutura (Stancl Tenancy)
 */
class TenantDatabaseService implements TenantDatabaseServiceInterface
{
    public function criarBancoDados(Tenant $tenant): void
    {
        try {
            // Buscar modelo Eloquent do tenant
            $tenantModel = TenantModel::findOrFail($tenant->id);
            
            // Recarregar para garantir que está persistido
            $tenantModel->refresh();
            
            // Obter nome do banco de dados que será criado
            $databaseName = $tenantModel->database()->getName();
            
            // Verificar se o banco de dados já existe usando conexão central (PostgreSQL)
            try {
                // Usar conexão padrão (pode ser 'pgsql' ou configurada no .env)
                $centralConnection = \Illuminate\Support\Facades\DB::connection();
                // PostgreSQL: verificar se o banco existe
                $databases = $centralConnection->select(
                    "SELECT datname FROM pg_database WHERE datname = ?",
                    [$databaseName]
                );
                
                if (!empty($databases)) {
                    // Banco já existe - verificar se está vazio ou tem dados
                    Log::warning('Banco de dados do tenant já existe', [
                        'tenant_id' => $tenant->id,
                        'database_name' => $databaseName,
                    ]);
                    
                    // Tentar conectar ao banco do tenant para verificar se está vazio
                    try {
                        // Inicializar tenancy temporariamente para verificar o banco
                        tenancy()->initialize($tenantModel);
                        try {
                            // PostgreSQL: listar tabelas do schema public
                            $tables = \Illuminate\Support\Facades\DB::select(
                                "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
                            );
                            
                            if (empty($tables)) {
                                // Banco existe mas está vazio - podemos usar
                                Log::info('Banco de dados existe mas está vazio, usando banco existente', [
                                    'tenant_id' => $tenant->id,
                                    'database_name' => $databaseName,
                                ]);
                                tenancy()->end();
                                return; // Não precisa criar, já existe e está vazio
                            } else {
                                // Banco existe e tem tabelas
                                Log::warning('Banco de dados existe e tem tabelas', [
                                    'tenant_id' => $tenant->id,
                                    'database_name' => $databaseName,
                                    'tables_count' => count($tables),
                                ]);
                                tenancy()->end();
                                throw new \Exception("Banco de dados '{$databaseName}' já existe e contém dados. Se você está tentando recriar a empresa, por favor, delete o banco de dados manualmente ou entre em contato com o suporte.");
                            }
                        } finally {
                            if (tenancy()->initialized) {
                                tenancy()->end();
                            }
                        }
                    } catch (\Exception $e) {
                        // Não conseguiu conectar - banco pode estar corrompido ou inacessível
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                        Log::error('Erro ao verificar banco existente do tenant', [
                            'tenant_id' => $tenant->id,
                            'database_name' => $databaseName,
                            'error' => $e->getMessage(),
                        ]);
                        throw new \Exception("Banco de dados '{$databaseName}' já existe mas não está acessível. Por favor, verifique o banco de dados ou entre em contato com o suporte.");
                    }
                }
            } catch (\Exception $checkException) {
                // Se não conseguir verificar, continuar tentando criar
                Log::debug('Não foi possível verificar se banco existe, tentando criar', [
                    'tenant_id' => $tenant->id,
                    'error' => $checkException->getMessage(),
                ]);
            }
            
            // Criar banco de dados
            CreateDatabase::dispatchSync($tenantModel);
        } catch (\Exception $e) {
            // Verificar se o erro é porque o banco já existe
            if (str_contains($e->getMessage(), 'already exists') || 
                (str_contains($e->getMessage(), 'Database') && str_contains($e->getMessage(), 'exists'))) {
                
                Log::warning('Banco de dados já existe (erro capturado)', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Tentar verificar se o banco está vazio e pode ser reutilizado
                try {
                    $tenantModel = TenantModel::findOrFail($tenant->id);
                    tenancy()->initialize($tenantModel);
                    try {
                        // PostgreSQL: listar tabelas do schema public
                        $tables = \Illuminate\Support\Facades\DB::select(
                            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
                        );
                        
                        if (empty($tables)) {
                            // Banco existe mas está vazio - podemos usar
                            Log::info('Banco de dados já existe mas está vazio, usando banco existente', [
                                'tenant_id' => $tenant->id,
                            ]);
                            tenancy()->end();
                            return; // Não precisa criar, já existe e está vazio
                        }
                    } finally {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                    }
                } catch (\Exception $verifyException) {
                    // Não conseguiu verificar
                    Log::debug('Não foi possível verificar se banco está vazio', [
                        'tenant_id' => $tenant->id,
                        'error' => $verifyException->getMessage(),
                    ]);
                }
                
                $databaseName = $tenantModel->database()->getName() ?? "tenant_{$tenant->id}";
                throw new \Exception("Banco de dados '{$databaseName}' já existe. Se você está tentando recriar a empresa, por favor, delete o banco de dados manualmente ou entre em contato com o suporte.");
            }
            
            Log::error('Erro ao criar banco do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Erro ao criar o banco de dados da empresa: ' . $e->getMessage());
        }
    }

    public function executarMigrations(Tenant $tenant): void
    {
        $tenantModel = TenantModel::findOrFail($tenant->id);
        MigrateDatabase::dispatchSync($tenantModel);
    }
}


