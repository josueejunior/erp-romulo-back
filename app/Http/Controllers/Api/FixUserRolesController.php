<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Permission\Models\Role;

class FixUserRolesController extends Controller
{
    /**
     * Endpoint para corrigir/atribuir role ao usuário logado
     * Útil para debug e correção de problemas
     */
    public function fixCurrentUserRole(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado'], 401);
        }

        $requestedRole = $request->input('role', 'Administrador');
        
        // Verificar se a role existe
        $role = Role::where('name', $requestedRole)->first();
        
        if (!$role) {
            return response()->json([
                'message' => 'Role não encontrada. Roles disponíveis: ' . implode(', ', Role::pluck('name')->toArray()),
                'available_roles' => Role::pluck('name')->toArray()
            ], 404);
        }

        // Atribuir role ao usuário
        $user->syncRoles([$requestedRole]);
        
        // Limpar cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role atribuída com sucesso',
            'user' => $user->email,
            'role' => $requestedRole,
            'all_roles' => $user->getRoleNames()->toArray()
        ]);
    }

    /**
     * Endpoint para listar roles do usuário atual
     */
    public function getCurrentUserRoles()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado'], 401);
        }

        return response()->json([
            'user' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'all_available_roles' => Role::pluck('name')->toArray()
        ]);
    }
}



