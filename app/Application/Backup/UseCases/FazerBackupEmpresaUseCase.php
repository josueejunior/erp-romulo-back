<?php

declare(strict_types=1);

namespace App\Application\Backup\UseCases;

use App\Domain\Exceptions\DomainException;
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
    /**
     * Executa backup dos dados de uma empresa do banco central
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

        // Verificar se empresa existe
        $empresa = DB::connection(config('tenancy.database.central_connection', config('database.default')))
            ->table('empresas')
            ->where('id', $empresaId)
            ->first();

        if (!$empresa) {
            throw new DomainException('Empresa não encontrada.', 404);
        }

        // Obter nome do banco central
        $centralConnection = config('tenancy.database.central_connection', config('database.default'));
        $databaseName = config("database.connections.{$centralConnection}.database");
        
        if (!$databaseName) {
            throw new DomainException('Nome do banco central não configurado.', 500);
        }

        // Configurações do banco central (para pg_dump - PostgreSQL)
        $dbHost = config("database.connections.{$centralConnection}.host", 'localhost');
        $dbPort = config("database.connections.{$centralConnection}.port", 5432);
        $dbUser = config("database.connections.{$centralConnection}.username");
        $dbPassword = config("database.connections.{$centralConnection}.password");

        if (!$dbUser || !$dbPassword) {
            throw new DomainException('Credenciais do banco de dados não configuradas.', 500);
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
            'database' => $databaseName,
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
            fwrite($fileHandle, "-- Banco: {$databaseName}\n");
            fwrite($fileHandle, "\n");
            fwrite($fileHandle, "BEGIN;\n\n");

            // Lista de tabelas que têm empresa_id e devem ser incluídas no backup
            $tabelasComEmpresaId = $this->obterTabelasComEmpresaId($centralConnection, $empresaId);

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

                // Obter estrutura da tabela primeiro
                $this->escreverEstruturaTabela($fileHandle, $tabela, $centralConnection, $dbHost, $dbPort, $dbUser, $dbPassword);

                // Escrever dados da empresa
                $this->escreverDadosTabela($fileHandle, $tabela, $dados);

                fwrite($fileHandle, "\n");
            }

            // Incluir também a tabela empresas (a própria empresa)
            $empresaData = DB::connection($centralConnection)
                ->table('empresas')
                ->where('id', $empresaId)
                ->first();

            if ($empresaData) {
                fwrite($fileHandle, "-- ============================================\n");
                fwrite($fileHandle, "-- Tabela: empresas (dados da empresa)\n");
                fwrite($fileHandle, "-- ============================================\n\n");
                
                $this->escreverEstruturaTabela($fileHandle, 'empresas', $centralConnection, $dbHost, $dbPort, $dbUser, $dbPassword);
                $this->escreverDadosTabela($fileHandle, 'empresas', [$empresaData]);
                fwrite($fileHandle, "\n");
            }

            // Incluir relacionamentos (ex: empresa_user, etc)
            $this->escreverRelacionamentos($fileHandle, $centralConnection, $empresaId);

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
                'database' => $databaseName,
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
     */
    private function obterTabelasComEmpresaId(string $connection, int $empresaId): array
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
     */
    private function escreverEstruturaTabela($fileHandle, string $tabela, string $connection, string $dbHost, int $dbPort, string $dbUser, string $dbPassword): void
    {
        // Usar pg_dump apenas para estrutura (--schema-only)
        $command = [
            'pg_dump',
            '-h', $dbHost,
            '-p', (string) $dbPort,
            '-U', $dbUser,
            '-d', config("database.connections.{$connection}.database"),
            '-t', $tabela,
            '--schema-only',
            '--no-owner',
            '--no-acl',
        ];

        $process = new Process($command);
        $env = $_ENV;
        $env['PGPASSWORD'] = $dbPassword;
        $process->setEnv($env);
        $process->setTimeout(60);

        $process->run(function ($type, $buffer) use ($fileHandle) {
            if (Process::OUT === $type) {
                // Filtrar apenas comandos CREATE TABLE
                if (strpos($buffer, 'CREATE TABLE') !== false || 
                    strpos($buffer, 'ALTER TABLE') !== false ||
                    strpos($buffer, 'CREATE SEQUENCE') !== false ||
                    strpos($buffer, 'CREATE INDEX') !== false) {
                    fwrite($fileHandle, $buffer);
                }
            }
        });
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
     * Escrever relacionamentos (tabelas pivot, etc)
     */
    private function escreverRelacionamentos($fileHandle, string $connection, int $empresaId): void
    {
        // Tabela empresa_user (relacionamento empresa-usuário)
        try {
            $empresaUsers = DB::connection($connection)
                ->table('empresa_user')
                ->where('empresa_id', $empresaId)
                ->get();

            if ($empresaUsers->isNotEmpty()) {
                fwrite($fileHandle, "-- ============================================\n");
                fwrite($fileHandle, "-- Tabela: empresa_user (relacionamentos)\n");
                fwrite($fileHandle, "-- ============================================\n\n");
                
                $this->escreverDadosTabela($fileHandle, 'empresa_user', $empresaUsers->toArray());
                fwrite($fileHandle, "\n");
            }
        } catch (\Exception $e) {
            // Tabela não existe - ignorar
        }

        // Adicione outros relacionamentos conforme necessário
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

