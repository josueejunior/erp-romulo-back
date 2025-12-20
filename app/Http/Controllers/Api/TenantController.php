<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

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

        // Adicionar campos opcionais se fornecidos
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

        // Criar banco de dados do tenant usando o job
        try {
            CreateDatabase::dispatchSync($tenant);
            // Executar migrations do tenant
            MigrateDatabase::dispatchSync($tenant);
        } catch (\Exception $e) {
            // Se falhar ao criar o banco, ainda retorna o tenant criado
            // O banco pode ser criado manualmente depois
            \Log::warning('Erro ao criar banco do tenant: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Empresa criada com sucesso!',
            'tenant' => [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
                'cnpj' => $tenant->cnpj,
                'email' => $tenant->email,
                'status' => $tenant->status,
            ],
        ], 201);
    }

    /**
     * Listar todos os tenants (apenas para administradores do sistema central)
     */
    public function index()
    {
        $tenants = Tenant::all();

        return response()->json([
            'data' => $tenants
        ]);
    }

    /**
     * Mostrar um tenant específico
     */
    public function show(Tenant $tenant)
    {
        return response()->json($tenant);
    }

    /**
     * Atualizar um tenant
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'razao_social' => 'sometimes|required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
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

        // Regra de negócio: CNPJ não pode ser alterado após definido
        if (array_key_exists('cnpj', $validated)) {
            $novoCnpj = $validated['cnpj'] ?? null;
            $cnpjAtual = $tenant->cnpj;

            if ($cnpjAtual && $novoCnpj && $novoCnpj !== $cnpjAtual) {
                return response()->json([
                    'message' => 'O CNPJ da empresa não pode ser alterado. Caso seja necessário, crie uma nova empresa.',
                    'current_cnpj' => $cnpjAtual,
                ], 422);
            }
        }

        $tenant->update($validated);

        return response()->json([
            'message' => 'Empresa atualizada com sucesso!',
            'tenant' => $tenant,
        ]);
    }

    /**
     * "Excluir" um tenant (empresa)
     * Regra de negócio: nunca excluir de fato, apenas inativar.
     */
    public function destroy(Tenant $tenant)
    {
        // Se já estiver inativa, não faz nada destrutivo
        if ($tenant->status !== 'inativa') {
            $tenant->status = 'inativa';
            $tenant->save();
        }

        return response()->json([
            'message' => 'Empresa inativada com sucesso!',
            'tenant' => $tenant,
        ]);
    }
}






