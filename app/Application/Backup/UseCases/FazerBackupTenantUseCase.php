<?php

declare(strict_types=1);

namespace App\Application\Backup\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

/**
 * Use Case para fazer backup de banco de dados de um tenant
 */
final class FazerBackupTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa backup do banco de dados de um tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array{filename: string, path: string, size: int, created_at: string}
     * @throws DomainException
     */
    public function executar(int $tenantId): array
    {
        Log::info('FazerBackupTenantUseCase::executar - Iniciando backup', [
            'tenant_id' => $tenantId,
        ]);

        // Buscar tenant
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenantDomain) {
            throw new DomainException('Tenant não encontrado.', 404);
        }

        $tenant = $this->tenantRepository->buscarModeloPorId($tenantId);
        if (!$tenant) {
            throw new DomainException('Tenant não encontrado.', 404);
        }

        // Obter configuração do banco de dados do tenant
        $databaseName = $tenant->database ?? null;
        if (!$databaseName) {
            throw new DomainException('Tenant não possui banco de dados configurado.', 400);
        }

        // Configurações do banco central (para mysqldump)
        $dbHost = config('database.connections.mysql.host', 'localhost');
        $dbPort = config('database.connections.mysql.port', 3306);
        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');

        if (!$dbUser || !$dbPassword) {
            throw new DomainException('Credenciais do banco de dados não configuradas.', 500);
        }

        // Criar nome do arquivo de backup
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_tenant_{$tenantId}_{$databaseName}_{$timestamp}.sql";
        $backupPath = storage_path('app/backups');
        
        // Criar diretório se não existir
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $fullPath = "{$backupPath}/{$filename}";

        Log::info('FazerBackupTenantUseCase::executar - Executando mysqldump', [
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'filename' => $filename,
        ]);

        try {
            // Executar mysqldump usando Process do Symfony
            // Usar variável de ambiente MYSQL_PWD para senha (mais seguro que --password=)
            // --single-transaction: garante consistência sem bloquear tabelas
            // --quick: usa menos memória
            // --lock-tables=false: não trava tabelas (já temos --single-transaction)
            // --routines: inclui stored procedures e functions
            // --triggers: inclui triggers
            // --add-drop-table: adiciona DROP TABLE antes de CREATE TABLE
            // --complete-insert: usa INSERT completos (mais seguro para restore)
            $command = [
                'mysqldump',
                '-h', $dbHost,
                '-P', (string) $dbPort,
                '-u', $dbUser,
                '--single-transaction',
                '--quick',
                '--lock-tables=false',
                '--routines',
                '--triggers',
                '--add-drop-table',
                '--complete-insert',
                $databaseName,
            ];

            // Criar processo sem usar shell (mais seguro)
            $process = new Process($command);
            
            // Definir variável de ambiente para senha (mais seguro que passar na linha de comando)
            $env = $_ENV;
            $env['MYSQL_PWD'] = $dbPassword;
            $process->setEnv($env);
            
            $process->setTimeout(600); // 10 minutos (backups podem demorar)
            
            // Abrir arquivo para escrita
            $fileHandle = fopen($fullPath, 'w');
            if (!$fileHandle) {
                throw new DomainException('Não foi possível criar arquivo de backup.', 500);
            }

            // Executar processo e escrever output diretamente no arquivo
            $process->run(function ($type, $buffer) use ($fileHandle) {
                if (Process::OUT === $type) {
                    fwrite($fileHandle, $buffer);
                }
            });

            fclose($fileHandle);

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();
                Log::error('FazerBackupTenantUseCase::executar - Erro ao executar mysqldump', [
                    'tenant_id' => $tenantId,
                    'database' => $databaseName,
                    'error' => $error,
                    'exit_code' => $process->getExitCode(),
                ]);
                // Limpar arquivo parcial se existir
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                throw new DomainException('Erro ao executar backup: ' . $error, 500);
            }

            // Verificar se o arquivo foi criado e tem conteúdo
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new DomainException('Backup criado mas arquivo está vazio ou não foi criado.', 500);
            }

            $fileSize = filesize($fullPath);

            Log::info('FazerBackupTenantUseCase::executar - Backup criado com sucesso', [
                'tenant_id' => $tenantId,
                'database' => $databaseName,
                'filename' => $filename,
                'size' => $fileSize,
            ]);

            return [
                'filename' => $filename,
                'path' => $fullPath,
                'size' => $fileSize,
                'size_human' => $this->formatBytes($fileSize),
                'created_at' => Carbon::now()->toIso8601String(),
                'tenant_id' => $tenantId,
                'tenant_razao_social' => $tenantDomain->razaoSocial,
                'database' => $databaseName,
            ];

        } catch (DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('FazerBackupTenantUseCase::executar - Erro inesperado', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DomainException('Erro ao criar backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lista todos os backups disponíveis
     * 
     * @return array
     */
    public function listarBackups(): array
    {
        $backupPath = storage_path('app/backups');
        
        if (!is_dir($backupPath)) {
            return [];
        }

        $files = glob("{$backupPath}/backup_tenant_*.sql");
        $backups = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $fileSize = filesize($file);
            $createdAt = filemtime($file);

            // Extrair informações do nome do arquivo: backup_tenant_{id}_{database}_{timestamp}.sql
            if (preg_match('/backup_tenant_(\d+)_([^_]+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql/', $filename, $matches)) {
                $backups[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => $fileSize,
                    'size_human' => $this->formatBytes($fileSize),
                    'created_at' => Carbon::createFromTimestamp($createdAt)->toIso8601String(),
                    'tenant_id' => (int) $matches[1],
                    'database' => $matches[2],
                ];
            }
        }

        // Ordenar por data de criação (mais recente primeiro)
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $backups;
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

