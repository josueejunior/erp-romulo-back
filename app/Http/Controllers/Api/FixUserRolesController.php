<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Auth\UseCases\GetUserRolesUseCase;
use App\Application\Auth\UseCases\FixUserRoleUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino para correção de roles de usuário
 * Segue padrão DDD - apenas recebe request e devolve response
 * Toda lógica de negócio está nos Use Cases
 */
class FixUserRolesController extends Controller
{
    public function __construct(
        private GetUserRolesUseCase $getUserRolesUseCase,
        private FixUserRoleUseCase $fixUserRoleUseCase,
    ) {}

    /**
     * Obter roles do usuário atual
     */
    public function getCurrentUserRoles(Request $request)
    {
        try {
            // Executar Use Case
            $data = $this->getUserRolesUseCase->executar($request->user());

            return response()->json([
                'data' => $data,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao obter roles do usuário', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao obter roles.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Corrigir role do usuário atual
     */
    public function fixCurrentUserRole(Request $request)
    {
        try {
            // Validação básica (apenas formato dos dados)
            $validated = $request->validate([
                'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
            ], [
                'role.in' => 'A role deve ser: Administrador, Operacional, Financeiro ou Consulta.',
            ]);

            // Executar Use Case
            $data = $this->fixUserRoleUseCase->executar(
                $request->user(),
                $validated['role'] ?? null
            );

            return response()->json([
                'message' => $data['message'],
                'success' => true,
                'data' => [
                    'role' => $data['role'],
                    'roles' => $data['roles'],
                    'perfil_empresa' => $data['perfil_empresa'],
                ],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao corrigir role do usuário', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erro ao corrigir role.',
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
