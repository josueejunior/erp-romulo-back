<?php

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Domain\Tenant\Services\TenantDatabasePoolServiceInterface;
use App\Models\Tenant as TenantModel;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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
            
            // 4. Encontrar o próximo número disponível (que não está em nenhum dos dois)
            $proximoNumero = 1;
            while (in_array($proximoNumero, $numerosExistentes)) {
                $proximoNumero++;
            }
            
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
     * Quando $forceCreate=true (ex.: cadastro público / nova conta), cria o banco mesmo sem TENANCY_CREATE_DATABASES.
     */
    public function criarBancoDados(Tenant $tenant, bool $forceCreate = false): void
    {
        $deveCriar = $forceCreate || config('tenancy.database.use_tenant_databases', env('TENANCY_CREATE_DATABASES', false));
        if (!$deveCriar) {
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
     * Quando $forceCreate=true (ex.: cadastro público / nova conta), executa mesmo sem TENANCY_CREATE_DATABASES.
     * Roda as migrations inline (mesma lógica do tenants:migrate) para garantir que todas as subpastas
     * (incl. permissions) sejam executadas antes de qualquer seeder/roles.
     */
    public function executarMigrations(Tenant $tenant, bool $forceCreate = false): void
    {
        $deveExecutar = $forceCreate || config('tenancy.database.use_tenant_databases', env('TENANCY_CREATE_DATABASES', false));
        if (!$deveExecutar) {
            Log::info('TenantDatabaseService::executarMigrations - Execução de migrations desabilitada (Single Database Tenancy)', [
                'tenant_id' => $tenant->id,
                'arquitetura' => 'Single Database - isolamento por empresa_id',
            ]);
            return;
        }

        $tenantModel = TenantModel::findOrFail($tenant->id);
        tenancy()->initialize($tenantModel);

        try {
            $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
            if (config('database.default') === $centralConnectionName) {
                $tenantDbName = $tenantModel->database()->getName();
                config(['database.connections.tenant.database' => $tenantDbName]);
                DB::purge('tenant');
                config(['database.default' => 'tenant']);
            }

            $tenantPath = database_path('migrations/tenant');
            if (!File::exists($tenantPath)) {
                throw new \RuntimeException("Diretório de migrations de tenant não encontrado: {$tenantPath}");
            }

            $subdirs = $this->getMigrationSubdirectories($tenantPath);
            if (empty($subdirs)) {
                Log::warning('TenantDatabaseService::executarMigrations - Nenhuma migration encontrada em tenant path', [
                    'tenant_id' => $tenant->id,
                    'path' => $tenantPath,
                ]);
                return;
            }

            $subdirs = $this->orderMigrationPaths($subdirs, $tenantPath);

            foreach ($subdirs as $subdir) {
                Artisan::call('migrate', [
                    '--path' => $subdir,
                    '--realpath' => true,
                    '--force' => true,
                ]);
            }

            Log::info('TenantDatabaseService::executarMigrations - Concluído', [
                'tenant_id' => $tenant->id,
                'paths_count' => count($subdirs),
            ]);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Diretórios que contêm arquivos de migration (mesma lógica do comando TenantMigrate).
     */
    private function getMigrationSubdirectories(string $basePath): array
    {
        $subdirs = [];
        foreach (File::allFiles($basePath) as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPath();
                if (!in_array($path, $subdirs, true)) {
                    $subdirs[] = $path;
                }
            }
        }
        return $subdirs;
    }

    /**
     * Ordena paths para rodar migrations na ordem correta considerando dependências:
     * 1. permissions (sem dependências)
     * 2. usuarios (sem dependências, mas referenciado por outros)
     * 3. empresas (sem dependências, mas referenciado por outros)
     * 4. fornecedores (sem dependências, mas referenciado por transportadoras, orcamentos, processos)
     * 5. orgaos (sem dependências, mas referenciado por processos)
     * 6. documentos (sem dependências, mas referenciado por processo_documentos)
     * 7. processos (depende de orgaos)
     * 8. processo_itens (depende de processos)
     * 9. contratos (depende de processos)
     * 10. autorizacoes_fornecimento (depende de processos e contratos)
     * 11. empenhos (depende de processos, contratos, autorizacoes_fornecimento)
     * 12. processo_item_vinculos (depende de processo_itens, contratos, autorizacoes_fornecimento, empenhos)
     * 13. orcamentos (depende de processos, processo_itens, fornecedores)
     * 14. notas_fiscais (depende de processos, empenhos, contratos, autorizacoes_fornecimento, fornecedores, processo_itens)
     * 15. assinaturas (depende de users e empresas)
     * 16. resto em ordem alfabética
     */
    private function orderMigrationPaths(array $paths, string $basePath): array
    {
        // Definir ordem de prioridade (menor número = maior prioridade)
        // Nota: processos precisa vir depois de contratos/autorizacoes/empenhos porque
        // processo_item_vinculos depende dessas tabelas, mas outras migrations de processos
        // precisam vir antes. A verificação de segurança na migration resolve isso.
        $priority = [
            'permissions' => 1,
            'usuarios' => 2,
            'empresas' => 3,
            'fornecedores' => 4,
            'orgaos' => 5,
            'documentos' => 6,
            'processos' => 7, // Maioria das migrations de processos vem aqui
            'contratos' => 8,
            'autorizacoes_fornecimento' => 9,
            'empenhos' => 10,
            // processo_item_vinculos está em processos mas precisa vir depois
            // A verificação de segurança na migration garante a ordem correta
            'orcamentos' => 11,
            'notas_fiscais' => 12,
            'assinaturas' => 13,
            'suporte' => 14,
        ];
        
        usort($paths, function ($a, $b) use ($basePath, $priority) {
            $aPriority = $this->getPathPriority($a, $basePath, $priority);
            $bPriority = $this->getPathPriority($b, $basePath, $priority);
            
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }
            
            // Se mesma prioridade, ordem alfabética
            return strcmp($a, $b);
        });
        
        return $paths;
    }
    
    /**
     * Obtém a prioridade de um path baseado no diretório
     */
    private function getPathPriority(string $path, string $basePath, array $priority): int
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $path);
        $dirName = explode(DIRECTORY_SEPARATOR, $relativePath)[0];
        
        return $priority[$dirName] ?? 999; // Prioridade baixa para diretórios não listados
    }
}




