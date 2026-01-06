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
use Illuminate\Support\Facades\Password;
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

    /**
     * Solicitar redefinição de senha (Esqueci minha senha)
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;
            $userFound = false;

            // 1. Tentar buscar no banco central (admin users)
            $adminUser = \App\Modules\Auth\Models\AdminUser::where('email', $email)->first();
            if ($adminUser) {
                // Para admin, usar o sistema padrão do Laravel (se configurado)
                // Por enquanto, vamos usar a mesma abordagem para todos
                $userFound = true;
            }

            // 2. Buscar em todos os tenants (multi-tenancy)
            if (!$userFound) {
                $tenants = \App\Models\Tenant::all();
                foreach ($tenants as $tenant) {
                    try {
                        tenancy()->initialize($tenant);
                        $user = \App\Modules\Auth\Models\User::where('email', $email)->first();
                        
                        if ($user) {
                            $userFound = true;
                            // Gerar token (garantir que seja salvo no banco central)
                            // O token precisa ser salvo no banco central, não no tenant
                            tenancy()->end(); // Finalizar tenancy antes de criar token
                            
                            // Usar conexão central para salvar o token
                            $token = \Illuminate\Support\Str::random(64);
                            $hashedToken = \Illuminate\Support\Facades\Hash::make($token);
                            
                            // Salvar token no banco central
                            \Illuminate\Support\Facades\DB::connection()->table('password_reset_tokens')
                                ->updateOrInsert(
                                    ['email' => $email],
                                    [
                                        'token' => $hashedToken,
                                        'created_at' => now(),
                                    ]
                                );
                            
                            // Enviar notificação com o token
                            $user->notify(new \App\Notifications\ResetPasswordNotification($token));
                            break;
                        }
                        
                        tenancy()->end();
                    } catch (\Exception $e) {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                        Log::warning('Erro ao buscar usuário no tenant', [
                            'tenant_id' => $tenant->id,
                            'email' => $email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Sempre retornar sucesso para prevenir enumeração de emails
            // (não revelar se o email existe ou não)
            return response()->json([
                'message' => 'Se o e-mail informado estiver cadastrado, você receberá um link para redefinir sua senha.',
                'success' => true,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao solicitar redefinição de senha', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retornar sucesso mesmo em caso de erro (prevenir enumeração)
            return response()->json([
                'message' => 'Se o e-mail informado estiver cadastrado, você receberá um link para redefinir sua senha.',
                'success' => true,
            ]);
        }
    }

    /**
     * Redefinir senha usando token
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $email = $request->email;
            $token = $request->token;
            $password = $request->password;

            // Verificar token no banco central
            $passwordReset = \Illuminate\Support\Facades\DB::connection()
                ->table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'message' => 'Token inválido ou expirado.',
                    'success' => false,
                ], 400);
            }

            // Verificar se o token é válido
            if (!Hash::check($token, $passwordReset->token)) {
                return response()->json([
                    'message' => 'Token inválido ou expirado.',
                    'success' => false,
                ], 400);
            }

            // Verificar se o token expirou (60 minutos)
            $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
            if ($createdAt->addMinutes(60)->isPast()) {
                return response()->json([
                    'message' => 'Token expirado. Solicite um novo link de redefinição.',
                    'success' => false,
                ], 400);
            }

            // Buscar usuário em todos os tenants
            $tenants = \App\Models\Tenant::all();
            $userUpdated = false;

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    $user = \App\Modules\Auth\Models\User::where('email', $email)->first();
                    
                    if ($user) {
                        // Atualizar senha
                        $user->password = Hash::make($password);
                        $user->save();
                        $userUpdated = true;
                    }
                    
                    tenancy()->end();
                    
                    if ($userUpdated) {
                        break;
                    }
                } catch (\Exception $e) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                    Log::warning('Erro ao atualizar senha no tenant', [
                        'tenant_id' => $tenant->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!$userUpdated) {
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                    'success' => false,
                ], 404);
            }

            // Deletar token usado
            \Illuminate\Support\Facades\DB::connection()
                ->table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'message' => 'Senha redefinida com sucesso!',
                'success' => true,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao redefinir senha', [
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

