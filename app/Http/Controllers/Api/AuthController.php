<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Garantir que não há tenancy ativo antes de começar
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Buscar o tenant que contém o usuário com este email
        $tenant = $this->findTenantByUserEmail($request->email);

        if (!$tenant) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas ou usuário não encontrado em nenhuma empresa.'],
            ]);
        }

        // Inicializar o contexto do tenant
        tenancy()->initialize($tenant);

        // Tentar autenticar o usuário dentro do tenant
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            tenancy()->end();
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Criar token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Retornar dados do usuário e tenant
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
                'cnpj' => $tenant->cnpj,
                'email' => $tenant->email,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        // Inicializar o contexto do tenant
        $tenant = Tenant::find($request->tenant_id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant não encontrado.'], 404);
        }

        tenancy()->initialize($tenant);

        // Criar usuário dentro do tenant
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Atribuir role padrão (se necessário)
        // $user->assignRole('Operacional');

        // Criar token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
                'cnpj' => $tenant->cnpj,
                'email' => $tenant->email,
            ],
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        tenancy()->end();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        // Garantir que o tenancy está inicializado
        if (!tenancy()->initialized) {
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }
        }
        
        // Obter dados do tenant atual
        $currentTenant = tenancy()->tenant;
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => $currentTenant ? [
                'id' => $currentTenant->id,
                'razao_social' => $currentTenant->razao_social,
                'cnpj' => $currentTenant->cnpj,
                'email' => $currentTenant->email,
            ] : null,
        ]);
    }

    /**
     * Busca o tenant que contém o usuário com o email fornecido
     */
    private function findTenantByUserEmail(string $email): ?Tenant
    {
        $tenants = Tenant::where('status', 'ativa')->get();
        $foundTenant = null;

        foreach ($tenants as $tenant) {
            try {
                // Garantir que não há tenancy ativo antes de inicializar
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                // Verificar se o banco do tenant existe antes de tentar inicializar
                try {
                    tenancy()->initialize($tenant);
                } catch (\Exception $e) {
                    // Se não conseguir inicializar (banco não existe), pular este tenant
                    \Log::warning("Erro ao inicializar tenant {$tenant->id}: " . $e->getMessage());
                    continue;
                }
                
                $user = User::where('email', $email)->first();
                
                if ($user) {
                    $foundTenant = $tenant;
                    // Finalizar para reinicializar no login
                    tenancy()->end();
                    break;
                }
                
                // Finalizar após verificar este tenant
                tenancy()->end();
            } catch (\Exception $e) {
                // Se houver erro, registrar e continuar para o próximo tenant
                \Log::warning("Erro ao buscar usuário no tenant {$tenant->id}: " . $e->getMessage());
                
                // Garantir que o tenancy está finalizado
                if (tenancy()->initialized) {
                    try {
                        tenancy()->end();
                    } catch (\Exception $endException) {
                        // Ignorar erro ao finalizar
                    }
                }
            }
        }

        return $foundTenant;
    }
}
