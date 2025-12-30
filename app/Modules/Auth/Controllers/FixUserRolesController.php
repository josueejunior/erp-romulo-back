<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Application\Auth\UseCases\GetUserRolesUseCase;
use App\Application\Auth\UseCases\FixUserRoleUseCase;
use App\Http\Requests\Auth\FixUserRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino para correção de roles de usuário
 * Segue padrão DDD - apenas recebe request e devolve response
 * Toda lógica de negócio está nos Use Cases
 * 
 * Organizado por módulo seguindo Arquitetura Hexagonal
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
            $user = $request->user();
            
            // Se for AdminUser, retornar array vazio (admin não tem roles no sistema de tenants)
            if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
                return response()->json([
                    'data' => [
                        'roles' => [],
                        'primary_role' => null,
                    ],
                ]);
            }
            
            // Executar Use Case para usuários comuns
            $data = $this->getUserRolesUseCase->executar($user);

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
     * Usa Form Request para validação
     */
    public function fixCurrentUserRole(FixUserRoleRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

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

