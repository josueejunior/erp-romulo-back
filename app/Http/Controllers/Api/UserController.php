<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar usuários com paginação
     */
    public function index(Request $request)
    {
        $query = User::withTrashed()->with(['roles', 'empresas']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(15);

        // Acrescenta roles no payload para facilitar o front
        $users->getCollection()->transform(function ($user) {
            $user->roles_list = $user->getRoleNames();
            return $user;
        });

        return response()->json($users);
    }

    /**
     * Criar usuário
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'min:8', new \App\Rules\StrongPassword()],
            'roles' => 'nullable|array',
            'roles.*' => 'string',
            'empresas' => 'nullable|array',
            'empresas.*' => 'integer|exists:empresas,id',
            'empresa_ativa_id' => 'nullable|integer|exists:empresas,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (!empty($validated['roles'])) {
            $roles = Role::whereIn('name', $validated['roles'])->pluck('name')->toArray();
            $user->syncRoles($roles);
        }

        // Vincular empresas (pivô empresa_user)
        if (!empty($validated['empresas'])) {
            $syncData = [];
            foreach ($validated['empresas'] as $empresaId) {
                $syncData[$empresaId] = ['perfil' => 'consulta'];
            }
            $user->empresas()->sync($syncData);
        }

        // Definir empresa ativa, se enviada e pertencente
        if (!empty($validated['empresa_ativa_id']) && $user->empresas->pluck('id')->contains($validated['empresa_ativa_id'])) {
            $user->empresa_ativa_id = $validated['empresa_ativa_id'];
            $user->save();
        }

        return response()->json([
            'message' => 'Usuário criado com sucesso!',
            'user' => $this->mapUserWithRoles($user),
        ], 201);
    }

    /**
     * Mostrar um usuário
     */
    public function show(User $user)
    {
        return response()->json($this->mapUserWithRoles($user));
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', new \App\Rules\StrongPassword()],
            'roles' => 'nullable|array',
            'roles.*' => 'string',
            'empresas' => 'nullable|array',
            'empresas.*' => 'integer|exists:empresas,id',
            'empresa_ativa_id' => 'nullable|integer|exists:empresas,id',
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

        $user->save();

        if (array_key_exists('roles', $validated)) {
            $roles = Role::whereIn('name', $validated['roles'] ?? [])->pluck('name')->toArray();
            $user->syncRoles($roles);
        }

        if (array_key_exists('empresas', $validated)) {
            $syncData = [];
            foreach (($validated['empresas'] ?? []) as $empresaId) {
                $syncData[$empresaId] = ['perfil' => 'consulta'];
            }
            $user->empresas()->sync($syncData);
        }

        if (array_key_exists('empresa_ativa_id', $validated)) {
            $empresaAtivaId = $validated['empresa_ativa_id'];
            if ($empresaAtivaId && $user->empresas()->where('empresas.id', $empresaAtivaId)->exists()) {
                $user->empresa_ativa_id = $empresaAtivaId;
                $user->save();
            } elseif ($empresaAtivaId === null) {
                $user->empresa_ativa_id = null;
                $user->save();
            }
        }

        return response()->json([
            'message' => 'Usuário atualizado com sucesso!',
            'user' => $this->mapUserWithRoles($user),
        ]);
    }

    /**
     * Remover usuário
     */
    public function destroy(User $user)
    {
        // Em vez de excluir, inativa (soft delete)
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'Você não pode inativar a si mesmo.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuário inativado com sucesso!',
        ]);
    }

    private function mapUserWithRoles(User $user)
    {
        $user->load(['roles', 'empresas']);
        $user->roles_list = $user->getRoleNames();
        $user->empresas_list = $user->empresas->map(function ($empresa) {
            return [
                'id' => $empresa->id,
                'razao_social' => $empresa->razao_social,
                'cnpj' => $empresa->cnpj,
            ];
        });
        return $user;
    }
}

