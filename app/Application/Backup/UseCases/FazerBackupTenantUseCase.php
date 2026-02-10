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

        // Obter nome do banco de dados usando o padrão do stancl/tenancy
        // Padrão: prefix + tenant_id + suffix (configurado em config/tenancy.php)
        $prefix = config('tenancy.database.prefix', 'tenant_');
        $suffix = config('tenancy.database.suffix', '');
        $databaseName = $prefix . $tenantId . $suffix;

        // Configurações do banco central (para pg_dump - PostgreSQL)
        $dbConnection = config('database.default', 'pgsql');
        $dbHost = config("database.connections.{$dbConnection}.host", 'localhost');
        $dbPort = config("database.connections.{$dbConnection}.port", 5432);
        $dbUser = config("database.connections.{$dbConnection}.username");
        $dbPassword = config("database.connections.{$dbConnection}.password");

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

        // Verificar se pg_dump está disponível
        $pgDumpCheck = new Process(['which', 'pg_dump']);
        $pgDumpCheck->run();
        if (!$pgDumpCheck->isSuccessful()) {
            throw new DomainException('pg_dump não está instalado. Instale o postgresql-client.', 500);
        }

        Log::info('FazerBackupTenantUseCase::executar - Executando pg_dump', [
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'filename' => $filename,
            'connection' => $dbConnection,
        ]);

        try {
            // Executar pg_dump usando Process do Symfony
            // Usar variável de ambiente PGPASSWORD para senha (mais seguro que passar na linha de comando)
            // --no-owner: não incluir comandos de ownership (útil para restore em outros ambientes)
            // --no-acl: não incluir comandos de ACL/permissões
            // --clean: adiciona comandos DROP antes de CREATE
            // --if-exists: usa IF EXISTS nos comandos DROP
            // Formato padrão é plain text (SQL), não precisa especificar -F p
            $command = [
                'pg_dump',
                '-h', $dbHost,
                '-p', (string) $dbPort,
                '-U', $dbUser,
                '-d', $databaseName,
                '--no-owner',
                '--no-acl',
                '--clean',
                '--if-exists',
            ];

            // Criar processo sem usar shell (mais seguro)
            $process = new Process($command);
            
            // Definir variável de ambiente para senha (mais seguro que passar na linha de comando)
            $env = $_ENV;
            $env['PGPASSWORD'] = $dbPassword;
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
                Log::error('FazerBackupTenantUseCase::executar - Erro ao executar pg_dump', [
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
            Log::warning('FazerBackupTenantUseCase::listarBackups - Diretório de backups não existe', [
                'path' => $backupPath,
            ]);
            return [];
        }

        $backups = [];

        // 🔍 Backups de TENANT (banco inteiro do tenant)
        $filesTenant = glob("{$backupPath}/backup_tenant_*.sql") ?: [];

        Log::info('FazerBackupTenantUseCase::listarBackups - Arquivos de tenant encontrados', [
            'path' => $backupPath,
            'count' => count($filesTenant),
            'files' => $filesTenant,
        ]);

        foreach ($filesTenant as $file) {
            $filename = basename($file);
            $fileSize = filesize($file);
            $createdAt = filemtime($file);

            // Extrair informações do nome do arquivo: backup_tenant_{id}_{database}_{timestamp}.sql
            // O nome do banco pode conter underscores (ex: tenant_2), então precisamos capturar tudo até o timestamp
            // Padrão: backup_tenant_{id}_{database}_{timestamp}.sql
            // Exemplo: backup_tenant_2_tenant_2_2026-01-13_11-15-13.sql
            // Usar padrão que captura tudo entre tenant_id e timestamp (que sempre começa com YYYY-MM-DD)
            if (preg_match('/^backup_tenant_(\d+)_(.+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql$/', $filename, $matches)) {
                $backups[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => $fileSize,
                    'size_human' => $this->formatBytes($fileSize),
                    'created_at' => Carbon::createFromTimestamp($createdAt)->toIso8601String(),
                    'tipo' => 'tenant',
                    'tenant_id' => (int) $matches[1],
                    'database' => $matches[2],
                ];
            } else {
                // Log para debug se o padrão não corresponder
                Log::warning('FazerBackupTenantUseCase::listarBackups - Arquivo não corresponde ao padrão esperado', [
                    'filename' => $filename,
                ]);
            }
        }

        // 🔍 Backups de EMPRESA (dump filtrado por empresa_id)
        $filesEmpresa = glob("{$backupPath}/backup_empresa_*.sql") ?: [];

        Log::info('FazerBackupTenantUseCase::listarBackups - Arquivos de empresa encontrados', [
            'path' => $backupPath,
            'count' => count($filesEmpresa),
            'files' => $filesEmpresa,
        ]);

        foreach ($filesEmpresa as $file) {
            $filename = basename($file);
            $fileSize = filesize($file);
            $createdAt = filemtime($file);

            // Padrão: backup_empresa_{empresaId}_{razaoSocialLimpa}_{timestamp}.sql
            // Exemplo: backup_empresa_1_EMPRESA_X_Y_2026-02-10_12-02-44.sql
            if (preg_match('/^backup_empresa_(\d+)_([A-Za-z0-9_]+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql$/', $filename, $matches)) {
                $empresaId = (int) $matches[1];
                $razaoSocialSanitizada = $matches[2];

                $backups[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => $fileSize,
                    'size_human' => $this->formatBytes($fileSize),
                    'created_at' => Carbon::createFromTimestamp($createdAt)->toIso8601String(),
                    'tipo' => 'empresa',
                    'empresa_id' => $empresaId,
                    'empresa_razao_social_sanitizada' => $razaoSocialSanitizada,
                ];
            } else {
                Log::warning('FazerBackupTenantUseCase::listarBackups - Arquivo de empresa não corresponde ao padrão esperado', [
                    'filename' => $filename,
                ]);
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

