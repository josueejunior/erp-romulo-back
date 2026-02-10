<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Backup\UseCases\FazerBackupTenantUseCase;
use App\Application\Backup\UseCases\FazerBackupEmpresaUseCase;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 🔥 DDD: Controller Admin para gerenciar backups de tenants
 * 
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos UseCases
 * 
 * Responsabilidades:
 * - Receber request HTTP
 * - Chamar UseCase apropriado
 * - Retornar response padronizado (ApiResponse)
 */
class AdminBackupController extends Controller
{
    public function __construct(
        private readonly FazerBackupTenantUseCase $fazerBackupTenantUseCase,
        private readonly FazerBackupEmpresaUseCase $fazerBackupEmpresaUseCase,
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
                    
                    // 🔥 NOVO: Buscar empresa_id associada ao tenant (primeira empresa do tenant)
                    $empresaId = null;
                    try {
                        // Usar método estático para buscar empresa_id
                        $empresaId = \App\Models\TenantEmpresa::findEmpresaIdByTenantId($tenantDomain->id);
                        
                        if (!$empresaId) {
                            // Se não encontrou na tabela tenant_empresas, tentar buscar diretamente no banco do tenant
                            Log::warning('AdminBackupController::listarTenants - Empresa não encontrada em tenant_empresas, tentando buscar no banco do tenant', [
                                'tenant_id' => $tenantDomain->id,
                            ]);
                            
                            // Tentar buscar empresa diretamente no banco do tenant
                            try {
                                $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                                if ($tenantModel) {
                                    // Inicializar tenancy temporariamente para buscar empresa
                                    tenancy()->initialize($tenantModel);
                                    
                                    // Buscar primeira empresa do tenant
                                    $empresa = \App\Models\Empresa::first();
                                    if ($empresa) {
                                        $empresaId = $empresa->id;
                                        // Criar mapeamento para próxima vez
                                        \App\Models\TenantEmpresa::createOrUpdateMapping($tenantDomain->id, $empresaId);
                                        Log::info('AdminBackupController::listarTenants - Empresa encontrada no banco do tenant e mapeamento criado', [
                                            'tenant_id' => $tenantDomain->id,
                                            'empresa_id' => $empresaId,
                                        ]);
                                    }
                                    
                                    tenancy()->end();
                                }
                            } catch (\Exception $e2) {
                                Log::error('AdminBackupController::listarTenants - Erro ao buscar empresa no banco do tenant', [
                                    'tenant_id' => $tenantDomain->id,
                                    'error' => $e2->getMessage(),
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorar erro se tabela não existir ou não conseguir buscar
                        Log::error('AdminBackupController::listarTenants - Erro ao buscar empresa_id', [
                            'tenant_id' => $tenantDomain->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                    
                    return [
                        'id' => $tenantDomain->id,
                        'razao_social' => $tenantDomain->razaoSocial ?? 'N/A',
                        'cnpj' => $tenantDomain->cnpj ?? null,
                        'database' => $databaseName,
                        'status' => $tenantDomain->status ?? 'ativa',
                        'empresa_id' => $empresaId, // 🔥 NOVO: empresa_id para backup por empresa
                        'created_at' => $tenant ? ($tenant->created_at ? $tenant->created_at->toIso8601String() : null) : null,
                    ];
                })
                ->filter(function ($tenant) {
                    // Filtrar qualquer resultado inválido que possa ter sido criado
                    return $tenant && isset($tenant['id']) && $tenant['id'] !== null;
                })
                ->values(); // Reindexar array

            // Criar paginator manual para resposta padronizada
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $tenants,
                $tenantsPaginator->total(),
                $tenantsPaginator->perPage(),
                $tenantsPaginator->currentPage(),
                [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]
            );

            return ApiResponse::paginated($paginator);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::listarTenants - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao listar tenants.', 500);
        }
    }

    /**
     * Faz backup de um tenant específico
     */
    public function fazerBackup(Request $request, int $tenantId): JsonResponse
    {
        try {
            $result = $this->fazerBackupTenantUseCase->executar($tenantId);

            return ApiResponse::success(
                'Backup criado com sucesso!',
                $result,
                201
            );

        } catch (DomainException $e) {
            return ApiResponse::error(
                $e->getMessage(),
                $e->getCode() ?: 400
            );

        } catch (\Exception $e) {
            Log::error('AdminBackupController::fazerBackup - Erro', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao criar backup.', 500);
        }
    }

    /**
     * Lista todos os backups disponíveis
     */
    public function listarBackups(Request $request): JsonResponse
    {
        try {
            $backups = $this->fazerBackupTenantUseCase->listarBackups();

            return ApiResponse::collection($backups);

        } catch (\Exception $e) {
            Log::error('AdminBackupController::listarBackups - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao listar backups.', 500);
        }
    }

    /**
     * Baixa um arquivo de backup
     */
    public function baixarBackup(string $filename): BinaryFileResponse|JsonResponse
    {
        try {
            $backupPath = storage_path('app/backups');
            $fullPath = "{$backupPath}/{$filename}";

            // Validar nome do arquivo (prevenir path traversal)
            // Aceita backups de tenant: backup_tenant_{id}_{database}_{timestamp}.sql
            // Aceita backups de empresa: backup_empresa_{id}_{razao_social}_{timestamp}.sql
            if (!preg_match('/^backup_(tenant|empresa)_\d+_.+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
                return ApiResponse::error('Nome de arquivo inválido.', 400);
            }

            if (!file_exists($fullPath)) {
                return ApiResponse::error('Arquivo de backup não encontrado.', 404);
            }

            // Retornar download diretamente (ApiResponse não suporta download, usar response()->download())
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

            return ApiResponse::error('Erro ao baixar backup.', 500);
        }
    }

    /**
     * 🔥 NOVO: Faz backup de uma empresa específica do banco central
     * Filtra todos os dados por empresa_id e gera SQL apenas dessa empresa
     */
    public function fazerBackupEmpresa(Request $request, int $empresaId): JsonResponse
    {
        try {
            $result = $this->fazerBackupEmpresaUseCase->executar($empresaId);

            return ApiResponse::success(
                'Backup da empresa criado com sucesso!',
                $result,
                201
            );

        } catch (DomainException $e) {
            return ApiResponse::error(
                $e->getMessage(),
                $e->getCode() ?: 400
            );

        } catch (\Exception $e) {
            Log::error('AdminBackupController::fazerBackupEmpresa - Erro', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao criar backup da empresa.', 500);
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
            // Aceita backups de tenant: backup_tenant_{id}_{database}_{timestamp}.sql
            // Aceita backups de empresa: backup_empresa_{id}_{razao_social}_{timestamp}.sql
            if (!preg_match('/^backup_(tenant|empresa)_\d+_.+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
                return ApiResponse::error('Nome de arquivo inválido.', 400);
            }

            if (!file_exists($fullPath)) {
                return ApiResponse::error('Arquivo de backup não encontrado.', 404);
            }

            unlink($fullPath);

            Log::info('AdminBackupController::deletarBackup - Backup deletado', [
                'filename' => $filename,
            ]);

            return ApiResponse::success('Backup deletado com sucesso!');

        } catch (\Exception $e) {
            Log::error('AdminBackupController::deletarBackup - Erro', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao deletar backup.', 500);
        }
    }
}

