<?php

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Domain\Tenant\Services\TenantDatabasePoolServiceInterface;
use App\Models\Tenant as TenantModel;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Implementação do serviço de banco de dados do tenant
 * Conhece detalhes de infraestrutura (Stancl Tenancy)
 */
class TenantDatabaseService implements TenantDatabaseServiceInterface
{
    public function __construct(
        private readonly ?TenantDatabasePoolServiceInterface $poolService = null,
    ) {}
    /**
     * Encontrar o próximo número de tenant disponível
     * Verifica quais bancos já existem E quais tenants já existem na tabela
     * Retorna o próximo número que não tem nem banco nem tenant
     */
    public function encontrarProximoNumeroDisponivel(): int
    {
        try {
            $centralConnection = \Illuminate\Support\Facades\DB::connection();
            
            // 1. Buscar todos os bancos que começam com 'tenant_'
            $databases = $centralConnection->select(
                "SELECT datname FROM pg_database WHERE datname LIKE 'tenant_%' ORDER BY datname"
            );
            
            // Extrair números dos bancos existentes
            $numerosBancos = [];
            foreach ($databases as $db) {
                $dbName = $db->datname;
                // Extrair número de 'tenant_X'
                if (preg_match('/^tenant_(\d+)$/', $dbName, $matches)) {
                    $numerosBancos[] = (int) $matches[1];
                }
            }
            
            // 2. Buscar todos os IDs de tenants existentes na tabela
            $tenantsExistentes = \App\Models\Tenant::pluck('id')->toArray();
            
            // 3. Combinar ambos os arrays (bancos e tenants)
            $numerosExistentes = array_unique(array_merge($numerosBancos, $tenantsExistentes));
            sort($numerosExistentes);

            // 4. Sempre usar o topo (max + 1), sem reutilizar "buracos".
            // Reutilizar IDs antigos pode colidir com resíduos de infraestrutura
            // (cache, storage, permissões, conexões) e causar falhas intermitentes.
            $maiorNumero = empty($numerosExistentes) ? 0 : max($numerosExistentes);
            $proximoNumero = $maiorNumero + 1;
            
            Log::info('Próximo número de tenant disponível encontrado', [
                'proximo_numero' => $proximoNumero,
                'numeros_bancos' => $numerosBancos,
                'numeros_tenants' => $tenantsExistentes,
                'numeros_existentes_combinados' => $numerosExistentes,
            ]);
            
            return $proximoNumero;
        } catch (\Exception $e) {
            Log::warning('Erro ao encontrar próximo número disponível, usando próximo disponível', [
                'error' => $e->getMessage(),
            ]);
            
            // Em caso de erro, tentar encontrar o próximo ID disponível na tabela
            try {
                $maxId = \App\Models\Tenant::max('id') ?? 0;
                return $maxId + 1;
            } catch (\Exception $e2) {
                // Se tudo falhar, começar do 1
                return 1;
            }
        }
    }

    /**
     * Criar banco de dados do tenant
     * 
     * 🔥 ARQUITETURA SINGLE DATABASE:
     * Este método só cria banco se TENANCY_CREATE_DATABASES=true
     * Por padrão, usando Single Database Tenancy (isolamento por empresa_id no banco central)
     */
    public function criarBancoDados(Tenant $tenant): void
    {
        // Se não estiver configurado para criar bancos separados, pular
        if (!env('TENANCY_CREATE_DATABASES', false)) {
            Log::info('TenantDatabaseService::criarBancoDados - Criação de banco desabilitada (Single Database Tenancy)', [
                'tenant_id' => $tenant->id,
                'arquitetura' => 'Single Database - isolamento por empresa_id',
            ]);
            return;
        }
        
        try {
            // Buscar modelo Eloquent do tenant
            $tenantModel = TenantModel::findOrFail($tenant->id);
            
            // Recarregar para garantir que está persistido
            $tenantModel->refresh();
            
            // Obter nome do banco de dados que será criado
            $databaseNameEsperado = $tenantModel->database()->getName();
            
            // 🔥 MELHORIA: Tentar usar pool de bancos primeiro (reduz latência de 15s para 200ms)
            $databaseName = null;
            if ($this->poolService && $this->poolService->temBancosDisponiveis()) {
                $bancoDoPool = $this->poolService->obterBancoDoPool();
                
                if ($bancoDoPool) {
                    // Renomear banco do pool para o nome esperado do tenant
                    try {
                        DB::statement("ALTER DATABASE \"{$bancoDoPool}\" RENAME TO \"{$databaseNameEsperado}\"");
                        $databaseName = $databaseNameEsperado;
                        
                        Log::info('TenantDatabaseService - Banco obtido do pool e renomeado', [
                            'tenant_id' => $tenant->id,
                            'banco_pool' => $bancoDoPool,
                            'banco_final' => $databaseNameEsperado,
                        ]);
                    } catch (\Exception $renameException) {
                        Log::warning('TenantDatabaseService - Erro ao renomear banco do pool, criando novo', [
                            'tenant_id' => $tenant->id,
                            'error' => $renameException->getMessage(),
                        ]);
                        // Continuar com criação normal
                    }
                }
            }
            
            // Se não conseguiu usar pool, criar banco normalmente
            if (!$databaseName) {
                $databaseName = $databaseNameEsperado;
                
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
                
                // Criar banco de dados (apenas se não usou pool)
                CreateDatabase::dispatchSync($tenantModel);
            }
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
                
                // 🔥 NOVO: Se o banco já existe e tem dados, lançar exceção especial
                // O UseCase vai tratar isso e criar o tenant com próximo número disponível
                $proximoNumero = $this->encontrarProximoNumeroDisponivel();
                
                Log::warning('Banco já existe e tem dados, próximo número disponível encontrado', [
                    'tenant_id_atual' => $tenant->id,
                    'database_atual' => $databaseName,
                    'proximo_numero' => $proximoNumero,
                ]);
                
                throw new \App\Domain\Exceptions\DatabaseAlreadyExistsException(
                    "Banco de dados '{$databaseName}' já existe e contém dados. Próximo número disponível: {$proximoNumero}",
                    $proximoNumero
                );
            }
            
            Log::error('Erro ao criar banco do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Erro ao criar o banco de dados da empresa: ' . $e->getMessage());
        }
    }

    /**
     * Executar migrations do tenant
     * 
     * 🔥 ARQUITETURA SINGLE DATABASE:
     * Este método só executa migrations se TENANCY_CREATE_DATABASES=true
     * Por padrão, usando Single Database Tenancy (isolamento por empresa_id no banco central)
     */
    public function executarMigrations(Tenant $tenant): void
    {
        // Se não estiver configurado para criar bancos separados, pular
        if (!env('TENANCY_CREATE_DATABASES', false)) {
            Log::info('TenantDatabaseService::executarMigrations - Execução de migrations desabilitada (Single Database Tenancy)', [
                'tenant_id' => $tenant->id,
                'arquitetura' => 'Single Database - isolamento por empresa_id',
            ]);
            return;
        }
        
        $tenantModel = TenantModel::findOrFail($tenant->id);
        MigrateDatabase::dispatchSync($tenantModel);
    }
}




