<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\CrossTenant\Services\CrossTenantUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller para convite de usuários (usado pelo dono do tenant)
 * 
 * Fluxo 2: Dono do tenant convida um email para ter acesso
 * 
 * O usuário convidado pode já existir em outro tenant (cross-tenant)
 * ou ser totalmente novo no sistema.
 */
class ConviteUsuarioController extends Controller
{
    public function __construct(
        private CrossTenantUserService $crossTenantUserService,
    ) {}

    /**
     * Convidar usuário para o tenant atual
     * 
     * POST /api/v1/users/convidar
     * Body: { email, role?, empresa_id? }
     * 
     * O tenant é resolvido automaticamente pelo middleware (JWT)
     */
    public function convidar(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'role' => 'nullable|string|in:admin,operador,consulta',
                'empresa_id' => 'nullable|integer',
            ]);

            // Obter tenant do contexto (já resolvido pelo middleware)
            $tenantId = null;
            if ($request->attributes->has('auth')) {
                $payload = $request->attributes->get('auth');
                $tenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
            }

            if (!$tenantId) {
                $tenantId = $request->header('X-Tenant-ID') ? (int) $request->header('X-Tenant-ID') : null;
            }

            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant não identificado.',
                    'success' => false,
                ], 400);
            }

            // Verificar permissão: apenas admin do tenant pode convidar
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Não autenticado.',
                    'success' => false,
                ], 401);
            }

            // Não permitir convidar a si mesmo
            if ($user->email === $validated['email']) {
                return response()->json([
                    'message' => 'Você não pode convidar a si mesmo.',
                    'success' => false,
                ], 422);
            }

            Log::info('ConviteUsuarioController::convidar - Processando convite', [
                'email_convidado' => $validated['email'],
                'tenant_id' => $tenantId,
                'convidado_por' => $user->email,
                'role' => $validated['role'] ?? 'operador',
            ]);

            $result = $this->crossTenantUserService->vincularUsuarioATenant(
                email: $validated['email'],
                targetTenantId: $tenantId,
                targetEmpresaId: isset($validated['empresa_id']) ? (int) $validated['empresa_id'] : null,
                role: $validated['role'] ?? 'operador',
            );

            return response()->json([
                'message' => 'Usuário convidado com sucesso! Ele já pode acessar o sistema.',
                'success' => true,
                'data' => $result,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('ConviteUsuarioController::convidar - Erro', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Listar os tenants/empresas do usuário autenticado
     * 
     * GET /api/v1/users/meus-tenants
     * 
     * Usado para mostrar o seletor de tenant no frontend
     */
    public function meusTenants(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Não autenticado.',
                    'success' => false,
                ], 401);
            }

            $tenants = $this->crossTenantUserService->listarTenantsDoUsuario($user->email);

            return response()->json([
                'success' => true,
                'data' => [
                    'email' => $user->email,
                    'tenants' => $tenants,
                    'total' => count($tenants),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ConviteUsuarioController::meusTenants - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao buscar tenants.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Trocar o tenant ativo (gera novo JWT)
     * 
     * POST /api/v1/users/trocar-tenant
     * Body: { tenant_id }
     * 
     * Permite alternar entre tenants sem fazer re-login
     */
    public function trocarTenant(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|integer',
            ]);

            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Não autenticado.',
                    'success' => false,
                ], 401);
            }

            $result = $this->crossTenantUserService->trocarTenantAtivo(
                userId: $user->id,
                email: $user->email,
                newTenantId: (int) $validated['tenant_id'],
            );

            return response()->json([
                'message' => 'Tenant trocado com sucesso!',
                'success' => true,
                ...$result,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('ConviteUsuarioController::trocarTenant - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
