<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Facades\Tenancy;

class AdminUserController extends Controller
{
    /**
     * Listar usuários de uma empresa (tenant)
     */
    public function index(Request $request, Tenant $tenant)
    {
        // Garantir que não há tenancy ativo
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Inicializar contexto do tenant
        tenancy()->initialize($tenant);

        try {
            $query = User::withTrashed()->with(['roles', 'empresas']);

            if ($request->has('search') && $request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'ilike', "%{$request->search}%")
                      ->orWhere('email', 'ilike', "%{$request->search}%");
                });
            }

            $users = $query->orderBy('name')->paginate(15);

            // Transformar para incluir roles
            $users->getCollection()->transform(function ($user) {
                $user->roles_list = $user->getRoleNames();
                $user->empresa_ativa = $user->empresas->first();
                return $user;
            });

            return response()->json($users);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Mostrar um usuário específico
     */
    public function show(Request $request, Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->with(['roles', 'empresas'])->findOrFail($userId);
            $user->roles_list = $user->getRoleNames();
            $user->empresa_ativa = $user->empresas->first();
            
            return response()->json($user);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Criar usuário em uma empresa
     */
    public function store(Request $request, Tenant $tenant)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'role' => 'required|string|in:Administrador,Operacional,Financeiro,Consulta',
                'empresa_id' => 'required|exists:empresas,id',
            ]);

            // Criar usuário
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'empresa_ativa_id' => $validated['empresa_id'],
            ]);

            // Atribuir role
            $user->assignRole($validated['role']);

            // Associar usuário à empresa
            $user->empresas()->attach($validated['empresa_id'], [
                'perfil' => strtolower($validated['role'])
            ]);

            $user->load(['roles', 'empresas']);
            $user->roles_list = $user->getRoleNames();

            return response()->json([
                'message' => 'Usuário criado com sucesso!',
                'user' => $user,
            ], 201);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->findOrFail($userId);
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'password' => 'nullable|string|min:8',
                'role' => 'sometimes|required|string|in:Administrador,Operacional,Financeiro,Consulta',
                'empresa_id' => 'sometimes|required|exists:empresas,id',
            ]);

            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            if (isset($validated['empresa_id'])) {
                $user->empresa_ativa_id = $validated['empresa_id'];
                // Atualizar associação com empresa
                $user->empresas()->sync([$validated['empresa_id'] => [
                    'perfil' => strtolower($validated['role'] ?? $user->getRoleNames()->first() ?? 'consulta')
                ]]);
            }

            $user->save();

            // Atualizar role se fornecido
            if (isset($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            }

            $user->load(['roles', 'empresas']);
            $user->roles_list = $user->getRoleNames();

            return response()->json([
                'message' => 'Usuário atualizado com sucesso!',
                'user' => $user,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Excluir/Inativar usuário
     */
    public function destroy(Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::findOrFail($userId);
            // Soft delete
            $user->delete();

            return response()->json([
                'message' => 'Usuário inativado com sucesso!',
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Reativar usuário
     */
    public function reactivate(Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->findOrFail($userId);
            $user->restore();

            return response()->json([
                'message' => 'Usuário reativado com sucesso!',
                'user' => $user,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Obter empresas disponíveis no tenant
     */
    public function empresas(Tenant $tenant)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $empresas = \App\Models\Empresa::all();
            return response()->json($empresas);
        } finally {
            tenancy()->end();
        }
    }
}
