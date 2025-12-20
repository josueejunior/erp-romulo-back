<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\AdminUser;
use App\Helpers\LogSanitizer;

class AdminAuthController extends Controller
{
    /**
     * Login do administrador central
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Garantir que não há tenancy ativo
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $admin = AdminUser::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            // Log tentativa de login falha (sanitizado)
            \Log::warning('Tentativa de login admin falhou', LogSanitizer::sanitize([
                'email' => $request->email,
                'ip' => $request->ip(),
            ]));
            
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Log login bem-sucedido (sanitizado)
        \Log::info('Login admin realizado com sucesso', LogSanitizer::sanitize([
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'ip' => $request->ip(),
        ]));

        // Criar token
        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    /**
     * Logout do administrador
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso!',
        ]);
    }

    /**
     * Obter dados do administrador autenticado
     */
    public function me(Request $request)
    {
        return response()->json([
            'admin' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }
}
