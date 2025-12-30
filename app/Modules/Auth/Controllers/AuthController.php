<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthResource;
use App\Application\Auth\DTOs\LoginDTO;
use App\Application\Auth\DTOs\RegisterDTO;
use App\Application\Auth\UseCases\LoginUseCase;
use App\Application\Auth\UseCases\RegisterUseCase;
use App\Application\Auth\UseCases\LogoutUseCase;
use App\Application\Auth\UseCases\GetUserUseCase;
use App\Application\Auth\UseCases\BuscarAdminUserPorEmailUseCase;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino para autenticação de usuários (tenant)
 * Segue padrão DDD - apenas recebe request e devolve response
 * Toda lógica de negócio está nos Use Cases
 * 
 * Organizado por módulo seguindo Arquitetura Hexagonal
 */
class AuthController extends Controller
{
    public function __construct(
        private LoginUseCase $loginUseCase,
        private RegisterUseCase $registerUseCase,
        private LogoutUseCase $logoutUseCase,
        private GetUserUseCase $getUserUseCase,
        private BuscarAdminUserPorEmailUseCase $buscarAdminUserPorEmailUseCase,
    ) {}

    /**
     * Login do usuário
     * Usa Form Request para validação
     */
    public function login(LoginRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Verificar se é admin - se for, autenticar como admin
            // Usar Use Case DDD para buscar admin user
            $adminUser = $this->buscarAdminUserPorEmailUseCase->executar($validated['email']);
            
            // Prevenir enumeração: sempre usar mesmo tempo de resposta
            // Verificar senha primeiro para evitar timing attacks
            $isValidPassword = false;
            if ($adminUser) {
                $isValidPassword = Hash::check($validated['password'], $adminUser->password);
            }
            
            // Se não for admin válido, continuar para verificação de usuário comum
            // Isso previne enumeração de emails
            if ($adminUser && $isValidPassword) {
                // Autenticar como admin
                $token = $adminUser->createToken('admin-token', ['admin'])->plainTextToken;
                
                // Usar Resource para padronizar resposta
                $authData = [
                    'user' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                    ],
                    'tenant' => null, // Admin não tem tenant
                    'empresa' => null, // Admin não tem empresa
                    'token' => $token,
                    'is_admin' => true,
                ];
                
                return response()->json([
                    'message' => 'Login realizado com sucesso!',
                    'success' => true,
                    ...(new AuthResource($authData))->toArray($request),
                ]);
            }

            // Criar DTO
            $dto = LoginDTO::fromRequest($request);

            // Executar Use Case (aqui está a lógica)
            $data = $this->loginUseCase->executar($dto);

            // Usar Resource para padronizar resposta
            $authData = array_merge($data, ['is_admin' => false]);
            
            return response()->json([
                'message' => 'Login realizado com sucesso!',
                'success' => true,
                ...(new AuthResource($authData))->toArray($request),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            // Prevenir enumeração: sempre retornar mensagem genérica
            // Não revelar se o email existe ou não
            $message = $e->getMessage();
            if (str_contains($message, 'Credenciais inválidas') || 
                str_contains($message, 'não encontrado')) {
                $message = 'Credenciais inválidas. Verifique seu e-mail e senha.';
            }
            
            return response()->json([
                'message' => $message,
                'errors' => ['email' => [$message]],
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
     * Usa Form Request para validação
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Criar DTO
            $dto = RegisterDTO::fromRequest($request);

            // Executar Use Case (aqui está a lógica)
            $data = $this->registerUseCase->executar($dto);

            // Retornar no formato esperado pelo frontend
            return response()->json([
                'message' => 'Usuário registrado com sucesso!',
                'success' => true,
                'user' => $data['user'],
                'tenant' => $data['tenant'],
                'empresa' => $data['empresa'],
                'token' => $data['token'],
                'is_admin' => false,
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

