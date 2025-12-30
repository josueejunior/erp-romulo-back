<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Assinatura\UseCases\ListarAssinaturasAdminUseCase;
use App\Application\Assinatura\UseCases\BuscarAssinaturaAdminUseCase;
use App\Application\Assinatura\UseCases\AtualizarAssinaturaAdminUseCase;
use App\Application\Tenant\UseCases\ListarTenantsParaFiltroUseCase;
use App\Http\Requests\Assinatura\AtualizarAssinaturaAdminRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller Admin para gerenciar assinaturas de todos os tenants
 * 
 * 100% DDD - Apenas recebe request e delega para Use Cases
 */
class AdminAssinaturaController extends Controller
{
    public function __construct(
        private ListarAssinaturasAdminUseCase $listarAssinaturasAdminUseCase,
        private BuscarAssinaturaAdminUseCase $buscarAssinaturaAdminUseCase,
        private AtualizarAssinaturaAdminUseCase $atualizarAssinaturaAdminUseCase,
        private ListarTenantsParaFiltroUseCase $listarTenantsParaFiltroUseCase,
    ) {}

    /**
     * Listar todas as assinaturas de todos os tenants
     */
    public function index(Request $request)
    {
        try {
            $filtros = [
                'tenant_id' => $request->tenant_id,
                'status' => $request->status,
                'search' => $request->search,
            ];

            // Executar Use Case
            $todasAssinaturas = $this->listarAssinaturasAdminUseCase->executar($filtros);

            // Paginação manual
            $perPage = $request->per_page ?? 15;
            $currentPage = $request->page ?? 1;
            $total = $todasAssinaturas->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginated = $todasAssinaturas->slice($offset, $perPage)->values();

            return response()->json([
                'data' => $paginated,
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar assinaturas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao listar assinaturas.'], 500);
        }
    }

    /**
     * Buscar assinatura específica
     */
    public function show(int $tenantId, int $assinaturaId)
    {
        try {
            // Executar Use Case
            $data = $this->buscarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId);

            return ApiResponse::item($data);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar assinatura', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
            ]);
            return response()->json(['message' => 'Erro ao buscar assinatura.'], 500);
        }
    }

    /**
     * Atualizar assinatura
     */
    public function update(AtualizarAssinaturaAdminRequest $request, int $tenantId, int $assinaturaId)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Executar Use Case
            $assinaturaDomain = $this->atualizarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId, $validated);

            // Buscar modelo para resposta
            $assinaturaModel = $this->buscarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId);

            return response()->json([
                'message' => 'Assinatura atualizada com sucesso',
                'data' => $assinaturaModel,
            ]);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar assinatura', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao atualizar assinatura.'], 500);
        }
    }

    /**
     * Listar tenants para filtro
     */
    public function tenants()
    {
        try {
            // Executar Use Case
            $tenants = $this->listarTenantsParaFiltroUseCase->executar();

            return response()->json([
                'data' => $tenants->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar tenants', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empresas.'], 500);
        }
    }
}

