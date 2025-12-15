<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    /**
     * Criar um novo tenant (empresa)
     * Esta rota deve estar fora do middleware de tenancy
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
        ]);

        $tenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'razao_social' => $validated['razao_social'],
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'ativa',
        ]);

        // O banco de dados será criado automaticamente pelo evento TenantCreated

        return response()->json([
            'message' => 'Tenant criado com sucesso!',
            'tenant' => [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
                'cnpj' => $tenant->cnpj,
            ],
        ], 201);
    }

    /**
     * Listar todos os tenants (apenas para administradores do sistema central)
     */
    public function index()
    {
        $tenants = Tenant::all(['id', 'razao_social', 'cnpj', 'email', 'status', 'created_at']);

        return response()->json($tenants);
    }

    /**
     * Mostrar um tenant específico
     */
    public function show(Tenant $tenant)
    {
        return response()->json([
            'id' => $tenant->id,
            'razao_social' => $tenant->razao_social,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'status' => $tenant->status,
            'created_at' => $tenant->created_at,
        ]);
    }
}




