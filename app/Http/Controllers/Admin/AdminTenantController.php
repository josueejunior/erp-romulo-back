<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesTenantOperations;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    use HandlesTenantOperations;

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }
    /**
     * Listar todas as empresas
     */
    public function index(Request $request)
    {
        return $this->listTenants($request);
    }

    /**
     * Mostrar uma empresa específica
     */
    public function show(Tenant $tenant)
    {
        // Recarregar o tenant do banco para garantir que todos os dados estão atualizados
        $tenant->refresh();
        
        // Garantir que todos os atributos sejam carregados
        $tenant->makeVisible($tenant->getFillable());
        
        // Converter para array e adicionar campos extras
        $data = $tenant->toArray();
        $data['tenancy_db_name'] = config('tenancy.database.prefix') . $tenant->id;
        
        return response()->json($data);
    }

    /**
     * Criar nova empresa com usuário administrador (opcional)
     */
    public function store(Request $request)
    {
        return $this->createTenant($request, false);
    }

    /**
     * Atualizar empresa
     */
    public function update(Request $request, Tenant $tenant)
    {
        return $this->updateTenant($request, $tenant);
    }

    /**
     * Excluir/Inativar empresa
     */
    public function destroy(Tenant $tenant)
    {
        $tenant = $this->tenantService->inactivateTenant($tenant);

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
        $tenant = $this->tenantService->reactivateTenant($tenant);

        return response()->json([
            'message' => 'Empresa reativada com sucesso!',
            'tenant' => $tenant,
        ]);
    }
}
