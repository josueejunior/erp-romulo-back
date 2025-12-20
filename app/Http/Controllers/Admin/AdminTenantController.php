<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class AdminTenantController extends Controller
{
    /**
     * Listar todas as empresas
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        // Filtro por status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Busca por razão social ou CNPJ
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'ilike', "%{$search}%")
                  ->orWhere('cnpj', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($tenants);
    }

    /**
     * Mostrar uma empresa específica
     */
    public function show(Tenant $tenant)
    {
        return response()->json($tenant);
    }

    /**
     * Criar nova empresa
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca',
            'pix' => 'nullable|string|max:255',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => 'nullable|string|max:14',
            'representante_legal_cargo' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ]);

        // Gerar ID único baseado na razão social
        $baseId = Str::slug($validated['razao_social']);
        $tenantId = $baseId;
        $counter = 1;
        
        // Garantir que o ID seja único
        while (Tenant::find($tenantId)) {
            $tenantId = $baseId . '-' . $counter;
            $counter++;
        }

        $tenantData = [
            'id' => $tenantId,
            'razao_social' => $validated['razao_social'],
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'ativa',
        ];

        // Adicionar campos opcionais
        $optionalFields = [
            'endereco', 'cidade', 'estado', 'cep', 'telefones', 'emails_adicionais',
            'banco', 'agencia', 'conta', 'tipo_conta', 'pix',
            'representante_legal_nome', 'representante_legal_cpf', 'representante_legal_cargo', 'logo'
        ];

        foreach ($optionalFields as $field) {
            if (isset($validated[$field])) {
                $tenantData[$field] = $validated[$field];
            }
        }

        $tenant = Tenant::create($tenantData);

        // Criar banco de dados do tenant
        try {
            CreateDatabase::dispatchSync($tenant);
            MigrateDatabase::dispatchSync($tenant);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar banco do tenant: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Empresa criada, mas houve erro ao criar o banco de dados. Verifique os logs.',
                'tenant' => $tenant,
            ], 201);
        }

        return response()->json([
            'message' => 'Empresa criada com sucesso!',
            'tenant' => $tenant,
        ], 201);
    }

    /**
     * Atualizar empresa
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'razao_social' => 'sometimes|required|string|max:255',
            'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj,' . $tenant->id,
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca',
            'pix' => 'nullable|string|max:255',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => 'nullable|string|max:14',
            'representante_legal_cargo' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ]);

        // Regra: CNPJ não pode ser alterado após definido
        if (isset($validated['cnpj']) && $tenant->cnpj && $validated['cnpj'] !== $tenant->cnpj) {
            return response()->json([
                'message' => 'O CNPJ da empresa não pode ser alterado.',
                'current_cnpj' => $tenant->cnpj,
            ], 422);
        }

        $tenant->update($validated);

        return response()->json([
            'message' => 'Empresa atualizada com sucesso!',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Excluir/Inativar empresa
     */
    public function destroy(Tenant $tenant)
    {
        // Inativar em vez de excluir
        if ($tenant->status !== 'inativa') {
            $tenant->status = 'inativa';
            $tenant->save();
        }

        return response()->json([
            'message' => 'Empresa inativada com sucesso!',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Reativar empresa
     */
    public function reactivate(Tenant $tenant)
    {
        $tenant->status = 'ativa';
        $tenant->save();

        return response()->json([
            'message' => 'Empresa reativada com sucesso!',
            'tenant' => $tenant,
        ]);
    }
}
