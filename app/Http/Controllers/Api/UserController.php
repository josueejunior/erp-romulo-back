<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends BaseApiController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Listar usuários
     */
    public function list(Request $request): JsonResponse
    {
        // Implementação básica - pode ser expandida conforme necessário
        $users = \App\Modules\Auth\Models\User::query()
            ->with(['empresas', 'roles'])
            ->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Obter usuário específico
     */
    public function get(Request $request, int $id): JsonResponse
    {
        $user = \App\Modules\Auth\Models\User::with(['empresas', 'roles'])->findOrFail($id);
        return response()->json(['data' => $user]);
    }

    /**
     * Criar usuário
     */
    public function store(Request $request): JsonResponse
    {
        // Implementação básica - pode ser expandida conforme necessário
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = \App\Modules\Auth\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        return response()->json(['data' => $user], 201);
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = \App\Modules\Auth\Models\User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * Deletar usuário
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = \App\Modules\Auth\Models\User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Usuário deletado com sucesso'], 204);
    }

    /**
     * Trocar empresa ativa do usuário autenticado
     */
    public function switchEmpresaAtiva(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não autenticado.'
                ], 401);
            }

            $validated = $request->validate([
                'empresa_ativa_id' => 'required|integer|exists:empresas,id',
            ]);

            $empresaId = $validated['empresa_ativa_id'];

            // Verificar se o usuário tem acesso a esta empresa
            $userModel = \App\Modules\Auth\Models\User::findOrFail($user->id);
            $temAcesso = $userModel->empresas()->where('empresas.id', $empresaId)->exists();

            if (!$temAcesso) {
                return response()->json([
                    'message' => 'Você não tem acesso a esta empresa.'
                ], 403);
            }

            // Atualizar empresa ativa usando o repository
            $userDomain = $this->userRepository->atualizarEmpresaAtiva($user->id, $empresaId);

            Log::info('Empresa ativa alterada', [
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
            ]);

            return response()->json([
                'message' => 'Empresa ativa alterada com sucesso.',
                'data' => [
                    'user_id' => $userDomain->id,
                    'empresa_ativa_id' => $empresaId,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao trocar empresa ativa', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao trocar empresa ativa: ' . $e->getMessage(),
            ], 500);
        }
    }
}

