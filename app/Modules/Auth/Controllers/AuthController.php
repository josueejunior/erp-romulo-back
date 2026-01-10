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
use App\Application\Auth\UseCases\SolicitarResetSenhaUseCase;
use App\Application\Auth\UseCases\RedefinirSenhaUseCase;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;
use OpenApi\Annotations as OA;

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
        private SolicitarResetSenhaUseCase $solicitarResetSenhaUseCase,
        private RedefinirSenhaUseCase $redefinirSenhaUseCase,
    ) {
        // 🔥 LOG CRÍTICO: Se este log aparecer, significa que o controller foi instanciado
        \Log::info('AuthController::__construct - ✅ Controller instanciado', [
            'memory_usage' => memory_get_usage(true),
        ]);
    }

    /**
     * Login do usuário
     * Usa Form Request para validação
     * 
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Autenticar usuário",
     *     description="Realiza login e retorna token JWT para autenticação",
     *     operationId="login",
     *     tags={"Autenticação"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com"),
     *             @OA\Property(property="password", type="string", format="password", example="senha123"),
     *             @OA\Property(property="tenant_id", type="string", example="1", description="ID do tenant (opcional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login realizado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login realizado com sucesso!"),
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="João Silva"),
     *                 @OA\Property(property="email", type="string", example="usuario@empresa.com")
     *             ),
     *             @OA\Property(property="tenant_id", type="string", example="1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciais inválidas",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function login(LoginRequest $request)
    {
        try {
            \Log::info('AuthController::login - Iniciando login', [
                'email' => $request->input('email'),
                'has_tenant_id' => !empty($request->input('tenant_id')),
            ]);
            
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Verificar se é admin - se for, autenticar como admin
            // Usar Use Case DDD para buscar admin user
            \Log::debug('AuthController::login - Buscando admin user');
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
                // 🔥 JWT STATELESS: Gerar token JWT para admin
                $jwtService = app(\App\Services\JWTService::class);
                $token = $jwtService->generateToken([
                    'user_id' => $adminUser->id,
                    'is_admin' => true,
                    'role' => 'admin',
                ]);
                
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
            \Log::debug('AuthController::login - Criando LoginDTO');
            $dto = LoginDTO::fromRequest($request);

            // Executar Use Case (aqui está a lógica)
            \Log::debug('AuthController::login - Executando LoginUseCase', [
                'email' => $dto->email,
                'has_tenant_id' => !empty($dto->tenantId),
            ]);
            $data = $this->loginUseCase->executar($dto);
            \Log::info('AuthController::login - LoginUseCase executado com sucesso');

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

    /**
     * Solicitar redefinição de senha (Esqueci minha senha)
     * 
     * ✅ Refatorado para usar Use Case
     * - Lógica de negócio movida para SolicitarResetSenhaUseCase
     * - Controller apenas recebe request e retorna response
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            \Log::info('AuthController::forgotPassword - Iniciando', [
                'email' => $request->email,
            ]);

            // Executar Use Case (agora retorna array com success e message)
            $result = $this->solicitarResetSenhaUseCase->executar($request->email);

            return response()->json([
                'message' => $result['message'],
                'success' => $result['success'],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            // Exceção de domínio (email não encontrado ou erro no envio)
            $statusCode = $e->getCode() ?: 400;
            
            Log::warning('AuthController::forgotPassword - Erro de domínio', [
                'email' => $request->email,
                'message' => $e->getMessage(),
                'code' => $statusCode,
            ]);
            
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
            ], $statusCode);
        } catch (\Exception $e) {
            Log::error('Erro ao solicitar redefinição de senha', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? null,
            ]);
            
            return response()->json([
                'message' => 'Erro ao processar solicitação. Tente novamente mais tarde ou entre em contato com o suporte.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Redefinir senha usando token
     * 
     * ✅ Refatorado para usar Use Case
     * - Lógica de negócio movida para RedefinirSenhaUseCase
     * - Validação de senha usando Value Object Senha
     * - Controller apenas recebe request e retorna response
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            Log::info('AuthController::resetPassword - Iniciando', [
                'email' => $request->email,
                'has_token' => !empty($request->token),
                'has_password' => !empty($request->password),
                'has_password_confirmation' => !empty($request->password_confirmation),
            ]);

            // Executar Use Case
            $this->redefinirSenhaUseCase->executar(
                $request->email,
                $request->token,
                $request->password
            );

            return response()->json([
                'message' => 'Senha redefinida com sucesso!',
                'success' => true,
            ]);

        } catch (ValidationException $e) {
            Log::warning('AuthController::resetPassword - Erro de validação', [
                'email' => $request->email,
                'errors' => $e->errors(),
            ]);
            
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            Log::warning('AuthController::resetPassword - Erro de domínio', [
                'email' => $request->email,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            // Se o código for 422 (validação de senha), retornar como erro de validação
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['password' => [$e->getMessage()]],
                    'success' => false,
                ], 422);
            }
            
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('Erro ao redefinir senha', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erro ao redefinir senha. Tente novamente.',
                'success' => false,
            ], 500);
        }
    }
}

