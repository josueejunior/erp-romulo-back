<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Auth\DTOs\LoginDTO;
use App\Application\Auth\DTOs\RegisterDTO;
use App\Application\Auth\UseCases\LoginUseCase;
use App\Application\Auth\UseCases\RegisterUseCase;
use App\Application\Auth\UseCases\LogoutUseCase;
use App\Application\Auth\UseCases\GetUserUseCase;
use App\Modules\Auth\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino para autenticação de usuários (tenant)
 * Segue padrão DDD - apenas recebe request e devolve response
 * Toda lógica de negócio está nos Use Cases
 */
class AuthController extends Controller
{
    public function __construct(
        private LoginUseCase $loginUseCase,
        private RegisterUseCase $registerUseCase,
        private LogoutUseCase $logoutUseCase,
        private GetUserUseCase $getUserUseCase,
    ) {}

    /**
     * Login do usuário
     */
    public function login(Request $request)
    {
        try {
            // Validação básica (apenas formato dos dados)
            // tenant_id é opcional - será detectado automaticamente pelo email
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'tenant_id' => 'nullable|string',
            ], [
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
            ]);

            // Verificar se é admin - se for, autenticar como admin
            $adminUser = AdminUser::where('email', $validated['email'])->first();
            
            if ($adminUser && Hash::check($validated['password'], $adminUser->password)) {
                // Autenticar como admin
                $token = $adminUser->createToken('admin-token', ['admin'])->plainTextToken;
                
                return response()->json([
                    'message' => 'Login realizado com sucesso!',
                    'success' => true,
                    'data' => [
                        'user' => [
                            'id' => $adminUser->id,
                            'name' => $adminUser->name,
                            'email' => $adminUser->email,
                        ],
                        'tenant' => null, // Admin não tem tenant
                        'empresa' => null, // Admin não tem empresa
                        'token' => $token,
                        'is_admin' => true,
                    ],
                ]);
            }

            // Criar DTO
            $dto = LoginDTO::fromRequest($request);

            // Executar Use Case (aqui está a lógica)
            $data = $this->loginUseCase->executar($dto);

            return response()->json([
                'message' => 'Login realizado com sucesso!',
                'success' => true,
                'data' => $data,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['email' => [$e->getMessage()]],
                'success' => false,
            ], 401);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer login', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erro ao fazer login.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Registro de novo usuário
     */
    public function register(Request $request)
    {
        try {
            // Validação básica (apenas formato dos dados)
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
                'tenant_id' => 'required|string',
                'empresa_id' => 'required|integer',
            ], [
                'name.required' => 'O nome é obrigatório.',
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
                'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
                'tenant_id.required' => 'O Tenant ID é obrigatório.',
                'empresa_id.required' => 'A empresa é obrigatória.',
            ]);

            // Criar DTO
            $dto = RegisterDTO::fromRequest($request);

            // Executar Use Case (aqui está a lógica)
            $data = $this->registerUseCase->executar($dto);

            return response()->json([
                'message' => 'Usuário registrado com sucesso!',
                'success' => true,
                'data' => $data,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['email' => [$e->getMessage()]],
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar usuário', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erro ao registrar usuário.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Logout do usuário
     */
    public function logout(Request $request)
    {
        try {
            // Executar Use Case
            $this->logoutUseCase->executar($request->user());

            return response()->json([
                'message' => 'Logout realizado com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer logout', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao fazer logout.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Obter dados do usuário autenticado
     */
    public function user(Request $request)
    {
        try {
            // Executar Use Case
            $data = $this->getUserUseCase->executar($request->user());

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter dados do usuário', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao obter dados.',
            ], 500);
        }
    }
}
