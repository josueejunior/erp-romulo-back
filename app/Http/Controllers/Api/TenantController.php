<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesTenantOperations;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    use HandlesTenantOperations, HasDefaultActions;

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
        $this->service = $tenantService; // Para HasDefaultActions
    }

    /**
     * API: Listar tenants (Route::module)
     */
    public function list(Request $request)
    {
        return $this->index($request);
    }

    /**
     * API: Buscar tenant (Route::module)
     */
    public function get(Request $request, Tenant $tenant)
    {
        return $this->show($tenant);
    }
    /**
     * Criar um novo tenant (empresa) com usuário administrador
     * Esta rota deve estar fora do middleware de tenancy
     * Admin é obrigatório na API pública
     */
    public function store(Request $request)
    {
        return $this->createTenant($request, true);
    }

    /**
     * Listar todos os tenants (apenas para administradores do sistema central)
     */
    public function index(Request $request)
    {
        return $this->listTenants($request);
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
        return $this->updateTenant($request, $tenant);
    }

    /**
     * "Excluir" um tenant (empresa)
     * Regra de negócio: nunca excluir de fato, apenas inativar.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant = $this->tenantService->inactivateTenant($tenant);

        return response()->json([
            'message' => 'Empresa inativada com sucesso!',
            'tenant' => $tenant,
        ]);
    }
}







