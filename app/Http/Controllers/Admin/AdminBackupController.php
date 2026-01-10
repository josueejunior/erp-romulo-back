<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Backup\UseCases\FazerBackupTenantUseCase;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminBackupController extends BaseApiController
{
    public function __construct(
        private readonly FazerBackupTenantUseCase $fazerBackupTenantUseCase,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Lista todos os tenants com informações de banco de dados
     */
    public function listarTenants(Request $request): JsonResponse
    {
        try {
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
                'per_page' => $request->get('per_page', 100),
                'page' => $request->get('page', 1),
            ]);

            $tenants = $tenantsPaginator->getCollection()
                ->filter(function ($tenantDomain) {
                    // Filtrar apenas tenants válidos com ID
                    return $tenantDomain && $tenantDomain->id !== null;
                })
                ->map(function ($tenantDomain) {
                    $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    
                    // Obter nome do banco de dados usando o padrão do stancl/tenancy
                    // Padrão: prefix + tenant_id + suffix (configurado em config/tenancy.php)
                    $prefix = config('tenancy.database.prefix', 'tenant_');
                    $suffix = config('tenancy.database.suffix', '');
                    $databaseName = $prefix . $tenantDomain->id . $suffix;
                    
                    return [
                        'id' => $tenantDomain->id,
                        'razao_social' => $tenantDomain->razaoSocial ?? 'N/A',
                        'cnpj' => $tenantDomain->cnpj ?? null,
                        'database' => $databaseName,
                        'status' => $tenantDomain->status ?? 'ativa',
                        'created_at' => $tenant ? ($tenant->created_at ? $tenant->created_at->toIso8601String() : null) : null,
                    ];
                })
                ->filter(function ($tenant) {
                    // Filtrar qualquer resultado inválido que possa ter sido criado
                    return $tenant && isset($tenant['id']) && $tenant['id'] !== null;
                })
                ->values(); // Reindexar array

            return response()->json([
                'data' => $tenants,
                'current_page' => $tenantsPaginator->currentPage(),
                'per_page' => $tenantsPaginator->perPage(),
                'total' => $tenantsPaginator->total(),
                'last_page' => $tenantsPaginator->lastPage(),
            ]);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::listarTenants - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao listar tenants.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Faz backup de um tenant específico
     */
    public function fazerBackup(Request $request, int $tenantId): JsonResponse
    {
        try {
            $result = $this->fazerBackupTenantUseCase->executar($tenantId);

            return response()->json([
                'message' => 'Backup criado com sucesso!',
                'data' => $result,
            ], 201);

        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() ?: 400);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::fazerBackup - Erro', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao criar backup.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista todos os backups disponíveis
     */
    public function listarBackups(Request $request): JsonResponse
    {
        try {
            $backups = $this->fazerBackupTenantUseCase->listarBackups();

            return response()->json([
                'data' => $backups,
                'total' => count($backups),
            ]);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::listarBackups - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao listar backups.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Baixa um arquivo de backup
     */
    public function baixarBackup(string $filename): StreamedResponse|JsonResponse
    {
        try {
            $backupPath = storage_path('app/backups');
            $fullPath = "{$backupPath}/{$filename}";

            // Validar nome do arquivo (prevenir path traversal)
            if (!preg_match('/^backup_tenant_\d+_[a-zA-Z0-9_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
                return response()->json([
                    'message' => 'Nome de arquivo inválido.',
                ], 400);
            }

            if (!file_exists($fullPath)) {
                return response()->json([
                    'message' => 'Arquivo de backup não encontrado.',
                ], 404);
            }

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/sql',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::baixarBackup - Erro', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao baixar backup.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deleta um arquivo de backup
     */
    public function deletarBackup(string $filename): JsonResponse
    {
        try {
            $backupPath = storage_path('app/backups');
            $fullPath = "{$backupPath}/{$filename}";

            // Validar nome do arquivo (prevenir path traversal)
            if (!preg_match('/^backup_tenant_\d+_[a-zA-Z0-9_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
                return response()->json([
                    'message' => 'Nome de arquivo inválido.',
                ], 400);
            }

            if (!file_exists($fullPath)) {
                return response()->json([
                    'message' => 'Arquivo de backup não encontrado.',
                ], 404);
            }

            unlink($fullPath);

            Log::info('AdminBackupController::deletarBackup - Backup deletado', [
                'filename' => $filename,
            ]);

            return response()->json([
                'message' => 'Backup deletado com sucesso!',
            ]);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::deletarBackup - Erro', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao deletar backup.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

