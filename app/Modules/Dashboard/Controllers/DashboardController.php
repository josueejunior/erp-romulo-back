<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Dashboard\Services\DashboardService;
use App\Services\RedisService;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    use HasAuthContext;

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * API: Obter dados do dashboard
     */
    public function index()
    {
        // Verificar se o plano tem acesso a dashboard
        $tenant = $this->getTenant();
        if (!$tenant || !$tenant->temAcessoDashboard()) {
            return response()->json([
                'message' => 'O dashboard não está disponível no seu plano. Faça upgrade para o plano Profissional ou superior.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = $this->getTenantId();
        
        // Tentar obter do cache primeiro (com empresa_id no cache key)
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "dashboard_{$tenantId}_{$empresa->id}";
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        $data = $this->dashboardService->obterDadosDashboard($empresa->id);

        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "dashboard_{$tenantId}_{$empresa->id}";
            RedisService::set($cacheKey, $data, 300);  
        }

        return response()->json($data);
    }
}

