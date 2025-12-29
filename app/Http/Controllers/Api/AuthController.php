<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Controller para autenticação de usuários (tenant)
 * Usa DDD - apenas recebe request e devolve response
 */
class AuthController extends Controller
{
    /**
     * Login do usuário
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'tenant_id' => 'required|string', // Tenant ID é obrigatório para login
            ], [
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
                'tenant_id.required' => 'O Tenant ID é obrigatório.',
            ]);

            // Buscar tenant no banco central
            $tenant = Tenant::find($validated['tenant_id']);
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado.',
                    'errors' => ['tenant_id' => ['Tenant não encontrado.']],
                ], 404);
            }

            // Inicializar contexto do tenant para buscar o usuário no banco correto
            tenancy()->initialize($tenant);

            try {
                // Buscar usuário no banco do tenant
                $user = User::where('email', $validated['email'])->first();

                if (!$user || !Hash::check($validated['password'], $user->password)) {
                    return response()->json([
                        'message' => 'Credenciais inválidas.',
                        'errors' => ['email' => ['Credenciais inválidas.']],
                    ], 401);
                }

                // Criar token com informações do tenant
                $token = $user->createToken('api-token', ['tenant_id' => $tenant->id])->plainTextToken;

                // Obter empresa ativa do usuário
                $empresaAtiva = null;
                if ($user->empresa_ativa_id) {
                    $empresaAtiva = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();
                } else {
                    $empresaAtiva = $user->empresas()->first();
                    if ($empresaAtiva) {
                        $user->empresa_ativa_id = $empresaAtiva->id;
                        $user->save();
                    }
                }

                return response()->json([
                    'message' => 'Login realizado com sucesso!',
                    'success' => true,
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'empresa_ativa_id' => $user->empresa_ativa_id,
                        ],
                        'tenant' => [
                            'id' => $tenant->id,
                            'razao_social' => $tenant->razao_social,
                        ],
                        'empresa' => $empresaAtiva ? [
                            'id' => $empresaAtiva->id,
                            'razao_social' => $empresaAtiva->razao_social,
                        ] : null,
                        'token' => $token,
                    ],
                ]);
            } finally {
                // Finalizar contexto do tenant
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer login', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Erro ao fazer login.'], 500);
        }
    }

    /**
     * Registro de novo usuário
     * Nota: Em um sistema multi-tenant, o registro geralmente é feito pelo admin
     * Esta implementação básica pode precisar de ajustes conforme a regra de negócio
     */
    public function register(Request $request)
    {
        try {
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

            // Buscar tenant
            $tenant = Tenant::find($validated['tenant_id']);
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado.',
                    'errors' => ['tenant_id' => ['Tenant não encontrado.']],
                ], 404);
            }

            // Inicializar contexto do tenant
            tenancy()->initialize($tenant);

            try {
                // Verificar se email já existe no tenant
                if (User::where('email', $validated['email'])->exists()) {
                    return response()->json([
                        'message' => 'Este e-mail já está cadastrado.',
                        'errors' => ['email' => ['Este e-mail já está cadastrado.']],
                    ], 422);
                }

                // Verificar se empresa existe no tenant
                $empresa = \App\Models\Empresa::find($validated['empresa_id']);
                if (!$empresa) {
                    return response()->json([
                        'message' => 'Empresa não encontrada neste tenant.',
                        'errors' => ['empresa_id' => ['Empresa não encontrada.']],
                    ], 404);
                }

                // Criar usuário
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'empresa_ativa_id' => $validated['empresa_id'],
                ]);

                // Associar usuário à empresa
                $user->empresas()->attach($validated['empresa_id'], ['perfil' => 'consulta']);

                // Atribuir role padrão
                $user->assignRole('Consulta');

                // Criar token
                $token = $user->createToken('api-token', ['tenant_id' => $tenant->id])->plainTextToken;

                return response()->json([
                    'message' => 'Usuário registrado com sucesso!',
                    'success' => true,
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'empresa_ativa_id' => $user->empresa_ativa_id,
                        ],
                        'tenant' => [
                            'id' => $tenant->id,
                            'razao_social' => $tenant->razao_social,
                        ],
                        'empresa' => [
                            'id' => $empresa->id,
                            'razao_social' => $empresa->razao_social,
                        ],
                        'token' => $token,
                    ],
                ], 201);
            } finally {
                // Finalizar contexto do tenant
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar usuário', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Erro ao registrar usuário.'], 500);
        }
    }

    /**
     * Logout do usuário
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout realizado com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer logout', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao fazer logout.'], 500);
        }
    }

    /**
     * Obter dados do usuário autenticado
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();

            // Obter empresa ativa
            $empresaAtiva = null;
            if ($user->empresa_ativa_id) {
                $empresaAtiva = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();
            } else {
                $empresaAtiva = $user->empresas()->first();
                if ($empresaAtiva) {
                    $user->empresa_ativa_id = $empresaAtiva->id;
                    $user->save();
                }
            }

            // Obter tenant do token ou do contexto
            $tenant = null;
            $tenantId = null;
            
            if ($user->currentAccessToken()) {
                $abilities = $user->currentAccessToken()->abilities;
                $tenantId = $abilities['tenant_id'] ?? null;
            }
            
            if ($tenantId) {
                // Buscar tenant no banco central (sem inicializar tenancy)
                $tenant = Tenant::find($tenantId);
            }

            return response()->json([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'empresa_ativa_id' => $user->empresa_ativa_id,
                    ],
                    'tenant' => $tenant ? [
                        'id' => $tenant->id,
                        'razao_social' => $tenant->razao_social,
                    ] : null,
                    'empresa' => $empresaAtiva ? [
                        'id' => $empresaAtiva->id,
                        'razao_social' => $empresaAtiva->razao_social,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter dados do usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao obter dados.'], 500);
        }
    }
}
