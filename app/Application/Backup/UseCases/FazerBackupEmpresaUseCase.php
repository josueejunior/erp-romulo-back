<?php

declare(strict_types=1);

namespace App\Application\Backup\UseCases;

use App\Domain\Exceptions\DomainException;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use App\Models\TenantEmpresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

/**
 * Use Case para fazer backup de uma empresa específica do banco central
 * 
 * 🔥 NOVO: Backup filtrado por empresa_id do banco central
 * Ao invés de fazer backup do banco do tenant, faz backup apenas dos dados
 * de uma empresa específica do banco central (erp_licitacoes)
 */
final class FazerBackupEmpresaUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Executa backup dos dados de uma empresa do banco do tenant
     * 
     * 🔥 CORREÇÃO: Empresas estão no banco do tenant, não no banco central
     * Primeiro encontra o tenant_id através de tenant_empresas, depois busca a empresa no tenant
     * 
     * @param int $empresaId ID da empresa
     * @return array{filename: string, path: string, size: int, created_at: string}
     * @throws DomainException
     */
    public function executar(int $empresaId): array
    {
        Log::info('FazerBackupEmpresaUseCase::executar - Iniciando backup', [
            'empresa_id' => $empresaId,
        ]);

        // 🔥 CORREÇÃO: Encontrar tenant_id através de tenant_empresas (banco central)
        $tenantId = TenantEmpresa::findTenantIdByEmpresaId($empresaId);
        
        if (!$tenantId) {
            // Tentar buscar empresa em todos os tenants como fallback
            Log::warning('FazerBackupEmpresaUseCase::executar - Empresa não encontrada em tenant_empresas, tentando buscar em todos os tenants', [
                'empresa_id' => $empresaId,
            ]);
            
            // Buscar todos os tenants e procurar a empresa em cada um
            $tenants = \App\Models\Tenant::all();
            foreach ($tenants as $tenant) {
                try {
                    $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);
                    if (!$tenantDomain) {
                        continue;
                    }
                    
                    $empresaEncontrada = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                        return \App\Models\Empresa::find($empresaId);
                    });
                    
                    if ($empresaEncontrada) {
                        $tenantId = $tenant->id;
                        // Criar mapeamento para próxima vez
                        TenantEmpresa::createOrUpdateMapping($tenantId, $empresaId);
                        Log::info('FazerBackupEmpresaUseCase::executar - Empresa encontrada no tenant e mapeamento criado', [
                            'empresa_id' => $empresaId,
                            'tenant_id' => $tenantId,
                        ]);
                        break;
                    }
                } catch (\Exception $e) {
                    Log::debug('FazerBackupEmpresaUseCase::executar - Erro ao buscar empresa no tenant', [
                        'tenant_id' => $tenant->id,
                        'empresa_id' => $empresaId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
            
            if (!$tenantId) {
                throw new DomainException("Empresa ID {$empresaId} não encontrada em nenhum tenant. Verifique se a empresa existe e está associada a um tenant.", 404);
            }
        }

        Log::info('FazerBackupEmpresaUseCase::executar - Tenant encontrado', [
            'empresa_id' => $empresaId,
            'tenant_id' => $tenantId,
        ]);

        // Buscar tenant domain
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenantDomain) {
            throw new DomainException('Tenant não encontrado.', 404);
        }

        // 🔥 CORREÇÃO: Buscar modelo Eloquent para obter nome do banco
        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
        if (!$tenantModel) {
            throw new DomainException('Modelo do tenant não encontrado.', 404);
        }

        // 🔥 CORREÇÃO: Obter nome do banco do tenant usando o modelo Eloquent
        $tenantDbName = $tenantModel->database()->getName();

        // 🔥 CORREÇÃO: Buscar empresa no banco do tenant usando AdminTenancyRunner
        $empresa = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
            return \App\Models\Empresa::find($empresaId);
        });

        if (!$empresa) {
            throw new DomainException('Empresa não encontrada no tenant.', 404);
        }
        
        // Configurações do banco (para pg_dump - PostgreSQL)
        // Usar as mesmas credenciais do banco central, mas o nome do banco será o do tenant
        $centralConnection = config('tenancy.database.central_connection', config('database.default'));
        $dbHost = config("database.connections.{$centralConnection}.host", 'localhost');
        $dbPort = (int) config("database.connections.{$centralConnection}.port", 5432); // 🔥 CORREÇÃO: Converter para int
        $dbUser = config("database.connections.{$centralConnection}.username");
        $dbPassword = config("database.connections.{$centralConnection}.password");

        if (!$dbUser || !$dbPassword) {
            throw new DomainException('Credenciais do banco de dados não configuradas.', 500);
        }
        
        if (!$tenantDbName) {
            throw new DomainException('Nome do banco do tenant não configurado.', 500);
        }

        // Criar nome do arquivo de backup
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $razaoSocialLimpa = preg_replace('/[^a-zA-Z0-9]/', '_', $empresa->razao_social ?? 'empresa');
        $filename = "backup_empresa_{$empresaId}_{$razaoSocialLimpa}_{$timestamp}.sql";
        $backupPath = storage_path('app/backups');
        
        // Criar diretório se não existir
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $fullPath = "{$backupPath}/{$filename}";

        // Verificar se pg_dump está disponível
        $pgDumpCheck = new Process(['which', 'pg_dump']);
        $pgDumpCheck->run();
        if (!$pgDumpCheck->isSuccessful()) {
            throw new DomainException('pg_dump não está instalado. Instale o postgresql-client.', 500);
        }
        
        Log::info('FazerBackupEmpresaUseCase::executar - Executando backup filtrado', [
            'empresa_id' => $empresaId,
            'tenant_id' => $tenantId,
            'tenant_database' => $tenantDbName,
            'filename' => $filename,
        ]);

        try {
            // Abrir arquivo para escrita
            $fileHandle = fopen($fullPath, 'w');
            if (!$fileHandle) {
                throw new DomainException('Não foi possível criar arquivo de backup.', 500);
            }

            // Escrever cabeçalho do backup
            fwrite($fileHandle, "-- Backup da Empresa ID: {$empresaId}\n");
            fwrite($fileHandle, "-- Razão Social: " . ($empresa->razao_social ?? 'N/A') . "\n");
            fwrite($fileHandle, "-- Data: " . Carbon::now()->toDateTimeString() . "\n");
            fwrite($fileHandle, "-- Tenant ID: {$tenantId}\n");
            fwrite($fileHandle, "-- Banco: {$tenantDbName}\n");
            fwrite($fileHandle, "\n");
            fwrite($fileHandle, "BEGIN;\n\n");

            // 🔥 CORREÇÃO: Buscar tabelas no banco do tenant usando AdminTenancyRunner
            $tabelasComEmpresaId = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                return $this->obterTabelasComEmpresaId($empresaId);
            });

            Log::info('FazerBackupEmpresaUseCase::executar - Tabelas encontradas', [
                'empresa_id' => $empresaId,
                'total_tabelas' => count($tabelasComEmpresaId),
                'tabelas' => array_keys($tabelasComEmpresaId),
            ]);

            // Para cada tabela, fazer dump apenas dos registros da empresa
            foreach ($tabelasComEmpresaId as $tabela => $dados) {
                if (empty($dados)) {
                    continue; // Pular tabelas vazias
                }

                fwrite($fileHandle, "-- ============================================\n");
                fwrite($fileHandle, "-- Tabela: {$tabela}\n");
                fwrite($fileHandle, "-- ============================================\n\n");

                // 🔥 CORREÇÃO: Obter estrutura da tabela do banco do tenant
                $tenantConnection = 'tenant';
                $this->escreverEstruturaTabela($fileHandle, $tabela, $tenantConnection, $dbHost, $dbPort, $dbUser, $dbPassword, $tenantDbName);

                // Escrever dados da empresa
                $this->escreverDadosTabela($fileHandle, $tabela, $dados);

                fwrite($fileHandle, "\n");
            }

            // Incluir também a tabela empresas (a própria empresa)
            // A empresa já foi buscada anteriormente, então apenas escrever no backup
            if ($empresa) {
                fwrite($fileHandle, "-- ============================================\n");
                fwrite($fileHandle, "-- Tabela: empresas (dados da empresa)\n");
                fwrite($fileHandle, "-- ============================================\n\n");
                
                // 🔥 CORREÇÃO: Usar banco do tenant para estrutura
                $tenantConnection = 'tenant';
                $this->escreverEstruturaTabela($fileHandle, 'empresas', $tenantConnection, $dbHost, $dbPort, $dbUser, $dbPassword, $tenantDbName);
                
                // Converter modelo para array
                $empresaArray = $empresa instanceof \Illuminate\Database\Eloquent\Model 
                    ? $empresa->getAttributes() 
                    : (array) $empresa;
                
                $this->escreverDadosTabela($fileHandle, 'empresas', [$empresaArray]);
                fwrite($fileHandle, "\n");
            }

            // 🔥 CORREÇÃO: Incluir relacionamentos no banco do tenant
            $relacionamentos = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                return $this->obterRelacionamentos($empresaId);
            });
            
            if (!empty($relacionamentos)) {
                $tenantConnection = 'tenant'; // Definir antes do loop
                foreach ($relacionamentos as $tabela => $dados) {
                    fwrite($fileHandle, "-- ============================================\n");
                    fwrite($fileHandle, "-- Tabela: {$tabela} (relacionamentos)\n");
                    fwrite($fileHandle, "-- ============================================\n\n");
                    
                    $this->escreverEstruturaTabela($fileHandle, $tabela, $tenantConnection, $dbHost, $dbPort, $dbUser, $dbPassword, $tenantDbName);
                    $this->escreverDadosTabela($fileHandle, $tabela, $dados);
                    fwrite($fileHandle, "\n");
                }
            }

            fwrite($fileHandle, "COMMIT;\n");

            fclose($fileHandle);

            // Verificar se o arquivo foi criado e tem conteúdo
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new DomainException('Backup criado mas arquivo está vazio ou não foi criado.', 500);
            }

            $fileSize = filesize($fullPath);

            Log::info('FazerBackupEmpresaUseCase::executar - Backup criado com sucesso', [
                'empresa_id' => $empresaId,
                'filename' => $filename,
                'size' => $fileSize,
            ]);

            return [
                'filename' => $filename,
                'path' => $fullPath,
                'size' => $fileSize,
                'size_human' => $this->formatBytes($fileSize),
                'created_at' => Carbon::now()->toIso8601String(),
                'empresa_id' => $empresaId,
                'empresa_razao_social' => $empresa->razao_social ?? 'N/A',
                'tenant_id' => $tenantId,
                'database' => $tenantDbName,
            ];

        } catch (DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('FazerBackupEmpresaUseCase::executar - Erro inesperado', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DomainException('Erro ao criar backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obter todas as tabelas que têm empresa_id e seus dados
     * 🔥 CORREÇÃO: Agora busca no banco do tenant (connection padrão já é 'tenant')
     */
    private function obterTabelasComEmpresaId(int $empresaId): array
    {
        $tabelas = [];
        
        // Lista de tabelas conhecidas que têm empresa_id
        $tabelasConhecidas = [
            'processos',
            'orcamentos',
            'empenhos',
            'contratos',
            'notas_fiscais',
            'documentos_habilitacao',
            'autorizacoes_fornecimento',
            'fornecedores',
            'users', // Usuários vinculados à empresa
            // Adicione outras tabelas conforme necessário
        ];

        foreach ($tabelasConhecidas as $tabela) {
            try {
                // 🔥 CORREÇÃO: Usar conexão padrão (já é 'tenant' quando chamado dentro do AdminTenancyRunner)
                $connection = config('database.default');
                
                // Verificar se a tabela existe e tem coluna empresa_id
                $temEmpresaId = DB::connection($connection)
                    ->select("
                        SELECT column_name 
                        FROM information_schema.columns 
                        WHERE table_name = ? AND column_name = 'empresa_id'
                    ", [$tabela]);

                if (!empty($temEmpresaId)) {
                    // Buscar dados da empresa nesta tabela
                    $dados = DB::connection($connection)
                        ->table($tabela)
                        ->where('empresa_id', $empresaId)
                        ->get()
                        ->toArray();

                    if (!empty($dados)) {
                        $tabelas[$tabela] = $dados;
                    }
                }
            } catch (\Exception $e) {
                // Tabela não existe ou erro ao acessar - pular
                Log::debug('FazerBackupEmpresaUseCase - Tabela não encontrada ou erro', [
                    'tabela' => $tabela,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $tabelas;
    }

    /**
     * Escrever estrutura da tabela (CREATE TABLE)
     * 🔥 CORREÇÃO: Agora aceita databaseName para usar banco do tenant
     */
    private function escreverEstruturaTabela($fileHandle, string $tabela, string $connection, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, ?string $databaseName = null): void
    {
        // 🔥 CORREÇÃO: Usar databaseName se fornecido, senão usar da configuração
        $dbName = $databaseName ?? config("database.connections.{$connection}.database");
        
        Log::debug('FazerBackupEmpresaUseCase::escreverEstruturaTabela - Executando pg_dump', [
            'tabela' => $tabela,
            'database' => $dbName,
            'host' => $dbHost,
            'port' => $dbPort,
        ]);
        
        // Usar pg_dump apenas para estrutura (--schema-only)
        // Especificar schema public explicitamente
        $command = [
            'pg_dump',
            '-h', $dbHost,
            '-p', (string) $dbPort,
            '-U', $dbUser,
            '-d', $dbName,
            '-n', 'public', // Especificar schema public
            '-t', "public.{$tabela}", // Especificar schema.tabela
            '--schema-only',
            '--no-owner',
            '--no-acl',
            '--no-privileges',
            '--no-tablespaces',
        ];

        $process = new Process($command);
        $env = $_ENV;
        $env['PGPASSWORD'] = $dbPassword;
        $process->setEnv($env);
        $process->setTimeout(60);

        // Capturar todo o output primeiro
        $output = '';
        $errors = '';
        
        $process->run(function ($type, $buffer) use (&$output, &$errors) {
            if (Process::OUT === $type) {
                $output .= $buffer;
            } elseif (Process::ERR === $type) {
                $errors .= $buffer;
            }
        });

        // Verificar se houve erro
        if (!$process->isSuccessful() || !empty($errors)) {
            Log::error('FazerBackupEmpresaUseCase::escreverEstruturaTabela - Erro ao executar pg_dump', [
                'tabela' => $tabela,
                'database' => $dbName,
                'exit_code' => $process->getExitCode(),
                'errors' => $errors,
                'output_preview' => substr($output, 0, 500),
            ]);
            
            // Tentar gerar CREATE TABLE manualmente usando informações do banco
            $this->escreverEstruturaTabelaManual($fileHandle, $tabela, $dbName, $dbHost, $dbPort, $dbUser, $dbPassword);
            return;
        }

        // Filtrar e escrever apenas o conteúdo relevante (remover headers do pg_dump)
        $lines = explode("\n", $output);
        $inRelevantSection = false;
        $relevantContent = [];
        
        foreach ($lines as $line) {
            // Ignorar linhas de configuração do pg_dump
            if (preg_match('/^(SET |SELECT pg_catalog|-- PostgreSQL|-- Dumped|\\\\)/', $line)) {
                continue;
            }
            
            // Começar a capturar quando encontrar CREATE ou ALTER
            if (preg_match('/^(CREATE|ALTER|COMMENT|GRANT|REVOKE)/i', $line)) {
                $inRelevantSection = true;
            }
            
            // Parar quando encontrar o final do dump
            if (preg_match('/^-- PostgreSQL database dump complete/i', $line)) {
                break;
            }
            
            // Capturar conteúdo relevante
            if ($inRelevantSection || trim($line) !== '') {
                $relevantContent[] = $line;
            }
        }
        
        // Escrever conteúdo filtrado
        if (!empty($relevantContent)) {
            fwrite($fileHandle, implode("\n", $relevantContent) . "\n");
        } else {
            Log::warning('FazerBackupEmpresaUseCase::escreverEstruturaTabela - Nenhum conteúdo relevante encontrado, tentando método manual', [
                'tabela' => $tabela,
                'database' => $dbName,
                'output_length' => strlen($output),
            ]);
            // Tentar método manual como fallback
            $this->escreverEstruturaTabelaManual($fileHandle, $tabela, $dbName, $dbHost, $dbPort, $dbUser, $dbPassword);
        }
    }

    /**
     * Gerar CREATE TABLE manualmente usando informações do banco
     */
    private function escreverEstruturaTabelaManual($fileHandle, string $tabela, string $dbName, string $dbHost, int $dbPort, string $dbUser, string $dbPassword): void
    {
        try {
            // Conectar ao banco do tenant temporariamente
            $tempConnection = 'temp_backup_' . uniqid();
            config([
                "database.connections.{$tempConnection}" => [
                    'driver' => 'pgsql',
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPassword,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                ],
            ]);

            // Verificar se a tabela existe
            $tableExists = DB::connection($tempConnection)
                ->select("SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists", [$tabela]);

            if (empty($tableExists) || !($tableExists[0]->exists ?? false)) {
                fwrite($fileHandle, "-- AVISO: Tabela {$tabela} não existe no banco {$dbName}\n\n");
                DB::purge($tempConnection);
                return;
            }

            // Obter estrutura da tabela
            $columns = DB::connection($tempConnection)
                ->select("
                    SELECT 
                        column_name,
                        data_type,
                        character_maximum_length,
                        numeric_precision,
                        numeric_scale,
                        is_nullable,
                        column_default
                    FROM information_schema.columns
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                    ORDER BY ordinal_position
                ", [$tabela]);

            if (empty($columns)) {
                fwrite($fileHandle, "-- AVISO: Não foi possível obter colunas da tabela {$tabela}\n\n");
                DB::purge($tempConnection);
                return;
            }

            // Gerar CREATE TABLE
            fwrite($fileHandle, "CREATE TABLE IF NOT EXISTS \"{$tabela}\" (\n");
            
            $columnDefs = [];
            foreach ($columns as $column) {
                $def = "    \"{$column->column_name}\" " . $this->getColumnTypeDefinition($column);
                if ($column->is_nullable === 'NO') {
                    $def .= ' NOT NULL';
                }
                if ($column->column_default !== null) {
                    $def .= ' DEFAULT ' . $column->column_default;
                }
                $columnDefs[] = $def;
            }
            
            fwrite($fileHandle, implode(",\n", $columnDefs) . "\n);\n\n");

            // Obter índices
            $indexes = DB::connection($tempConnection)
                ->select("
                    SELECT 
                        indexname,
                        indexdef
                    FROM pg_indexes
                    WHERE schemaname = 'public' 
                    AND tablename = ?
                    AND indexname NOT LIKE '%_pkey'
                ", [$tabela]);

            foreach ($indexes as $index) {
                fwrite($fileHandle, $index->indexdef . ";\n");
            }

            DB::purge($tempConnection);
        } catch (\Exception $e) {
            Log::error('FazerBackupEmpresaUseCase::escreverEstruturaTabelaManual - Erro', [
                'tabela' => $tabela,
                'database' => $dbName,
                'error' => $e->getMessage(),
            ]);
            fwrite($fileHandle, "-- ERRO: Não foi possível gerar estrutura manual da tabela {$tabela}\n");
            fwrite($fileHandle, "-- Erro: {$e->getMessage()}\n\n");
        }
    }

    /**
     * Converter tipo de coluna do PostgreSQL para definição SQL
     */
    private function getColumnTypeDefinition($column): string
    {
        $type = strtolower($column->data_type);
        
        switch ($type) {
            case 'character varying':
            case 'varchar':
                $length = $column->character_maximum_length ? "({$column->character_maximum_length})" : '';
                return "VARCHAR{$length}";
            
            case 'character':
            case 'char':
                $length = $column->character_maximum_length ? "({$column->character_maximum_length})" : '';
                return "CHAR{$length}";
            
            case 'text':
                return 'TEXT';
            
            case 'integer':
            case 'int':
            case 'int4':
                return 'INTEGER';
            
            case 'bigint':
            case 'int8':
                return 'BIGINT';
            
            case 'smallint':
            case 'int2':
                return 'SMALLINT';
            
            case 'numeric':
            case 'decimal':
                $precision = $column->numeric_precision ?? 10;
                $scale = $column->numeric_scale ?? 0;
                return "NUMERIC({$precision}, {$scale})";
            
            case 'real':
            case 'float4':
                return 'REAL';
            
            case 'double precision':
            case 'float8':
                return 'DOUBLE PRECISION';
            
            case 'boolean':
            case 'bool':
                return 'BOOLEAN';
            
            case 'date':
                return 'DATE';
            
            case 'time':
                return 'TIME';
            
            case 'timestamp':
            case 'timestamp without time zone':
                return 'TIMESTAMP';
            
            case 'timestamp with time zone':
                return 'TIMESTAMP WITH TIME ZONE';
            
            case 'json':
                return 'JSON';
            
            case 'jsonb':
                return 'JSONB';
            
            case 'uuid':
                return 'UUID';
            
            default:
                return strtoupper($type);
        }
    }

    /**
     * Escrever dados da tabela (INSERT)
     */
    private function escreverDadosTabela($fileHandle, string $tabela, array $dados): void
    {
        if (empty($dados)) {
            return;
        }

        // Obter colunas do primeiro registro
        $primeiroRegistro = (array) $dados[0];
        $colunas = array_keys($primeiroRegistro);

        foreach ($dados as $registro) {
            $registroArray = (array) $registro;
            $valores = [];

            foreach ($colunas as $coluna) {
                $valor = $registroArray[$coluna] ?? null;
                
                if ($valor === null) {
                    $valores[] = 'NULL';
                } elseif (is_numeric($valor)) {
                    $valores[] = $valor;
                } elseif (is_bool($valor)) {
                    $valores[] = $valor ? 'true' : 'false';
                } else {
                    // Escapar strings (substituir ' por '' e \ por \\)
                    $valorEscapado = str_replace(["'", "\\"], ["''", "\\\\"], (string) $valor);
                    $valores[] = "'{$valorEscapado}'";
                }
            }

            $colunasStr = implode(', ', array_map(fn($c) => "\"{$c}\"", $colunas));
            $valoresStr = implode(', ', $valores);
            
            fwrite($fileHandle, "INSERT INTO \"{$tabela}\" ({$colunasStr}) VALUES ({$valoresStr});\n");
        }
    }

    /**
     * Obter relacionamentos (tabelas pivot, etc)
     * 🔥 CORREÇÃO: Agora busca no banco do tenant (connection padrão já é 'tenant')
     */
    private function obterRelacionamentos(int $empresaId): array
    {
        $relacionamentos = [];
        
        // Tabela empresa_user (relacionamento empresa-usuário)
        try {
            $connection = config('database.default');
            $empresaUsers = DB::connection($connection)
                ->table('empresa_user')
                ->where('empresa_id', $empresaId)
                ->get();

            if ($empresaUsers->isNotEmpty()) {
                $relacionamentos['empresa_user'] = $empresaUsers->toArray();
            }
        } catch (\Exception $e) {
            // Tabela não existe - ignorar
            Log::debug('FazerBackupEmpresaUseCase - Tabela empresa_user não encontrada', [
                'error' => $e->getMessage(),
            ]);
        }

        // Adicione outros relacionamentos conforme necessário
        return $relacionamentos;
    }

    /**
     * Formata bytes para formato legível
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

