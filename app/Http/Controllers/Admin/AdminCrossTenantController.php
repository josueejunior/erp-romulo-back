<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\CrossTenant\Services\CrossTenantUserService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller Admin para gerenciar usuários cross-tenant
 * 
 * Fluxo 1: Admin vincula email a múltiplos tenants
 * Fluxo 2: Admin desvincula email de um tenant
 * Fluxo 3: Admin lista tenants de um email
 */
class AdminCrossTenantController extends Controller
{
    public function __construct(
        private CrossTenantUserService $crossTenantUserService,
    ) {}

    /**
     * Vincular usuário a um novo tenant
     * 
     * POST /api/admin/cross-tenant/vincular
     * Body: { email, tenant_id, empresa_id?, role? }
     */
    public function vincular(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'tenant_id' => 'required|integer|exists:tenants,id',
                'empresa_id' => 'nullable|integer',
                'role' => 'nullable|string|in:admin,operador,consulta',
                'password' => 'nullable|string|min:6',
            ]);

            Log::info('AdminCrossTenantController::vincular - Iniciando', [
                'email' => $validated['email'],
                'tenant_id' => $validated['tenant_id'],
                'empresa_id' => $validated['empresa_id'] ?? null,
                'role' => $validated['role'] ?? 'operador',
            ]);

            $result = $this->crossTenantUserService->vincularUsuarioATenant(
                email: $validated['email'],
                targetTenantId: (int) $validated['tenant_id'],
                targetEmpresaId: isset($validated['empresa_id']) ? (int) $validated['empresa_id'] : null,
                role: $validated['role'] ?? 'operador',
                password: $validated['password'] ?? null,
            );

            return ApiResponse::success(
                'Usuário vinculado ao tenant com sucesso!',
                $result,
                201
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Dados inválidos.', 422, null, $e->errors());
        } catch (\Exception $e) {
            Log::error('AdminCrossTenantController::vincular - Erro', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Desvincular usuário de um tenant
     * 
     * POST /api/admin/cross-tenant/desvincular
     * Body: { email, tenant_id }
     */
    public function desvincular(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'tenant_id' => 'required|integer|exists:tenants,id',
            ]);

            $result = $this->crossTenantUserService->desvincularUsuarioDeTenant(
                email: $validated['email'],
                tenantId: (int) $validated['tenant_id'],
            );

            if ($result) {
                return ApiResponse::success('Usuário desvinculado do tenant com sucesso!');
            }

            return ApiResponse::error('Usuário não encontrado no tenant.', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Dados inválidos.', 422, null, $e->errors());
        } catch (\Exception $e) {
            Log::error('AdminCrossTenantController::desvincular - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Listar tenants onde um email está cadastrado
     * 
     * GET /api/admin/cross-tenant/tenants-do-usuario?email=xxx
     */
    public function tenantsDoUsuario(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            $tenants = $this->crossTenantUserService->listarTenantsDoUsuario($validated['email']);

            return ApiResponse::item([
                'email' => $validated['email'],
                'tenants' => $tenants,
                'total' => count($tenants),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::error('Dados inválidos.', 422, null, $e->errors());
        } catch (\Exception $e) {
            Log::error('AdminCrossTenantController::tenantsDoUsuario - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
