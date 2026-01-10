<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Controller Admin para autenticação
 * Usa DDD - apenas recebe request e devolve response
 */
class AdminAuthController extends Controller
{
    /**
     * Login do administrador
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ], [
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
            ]);

            $admin = AdminUser::where('email', $validated['email'])->first();

            if (!$admin || !Hash::check($validated['password'], $admin->password)) {
                return response()->json([
                    'message' => 'Credenciais inválidas.',
                    'errors' => ['email' => ['Credenciais inválidas.']],
                ], 401);
            }

            // 🔥 JWT STATELESS: Gerar token JWT para admin
            $jwtService = app(\App\Services\JWTService::class);
            $token = $jwtService->generateToken([
                'user_id' => $admin->id,
                'is_admin' => true,
                'role' => 'admin',
            ]);

            return response()->json([
                'message' => 'Login realizado com sucesso!',
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                    ],
                    'token' => $token,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer login admin', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao fazer login.'], 500);
        }
    }

    /**
     * Logout do administrador
     */
    public function logout(Request $request)
    {
        try {
            // 🔥 JWT STATELESS: JWT não precisa ser deletado (stateless)
            // O frontend apenas remove o token do storage local.
            \Log::info('AdminAuthController::logout - Logout realizado', [
                'user_id' => $request->user()->id,
                'note' => 'JWT stateless - token removido apenas no frontend',
            ]);

            return response()->json([
                'message' => 'Logout realizado com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer logout admin', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao fazer logout.'], 500);
        }
    }

    /**
     * Obter dados do administrador autenticado
     */
    public function me(Request $request)
    {
        try {
            $admin = $request->user();

            return response()->json([
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter dados do admin', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao obter dados.'], 500);
        }
    }
}




