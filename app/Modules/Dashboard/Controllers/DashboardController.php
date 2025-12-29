<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Dashboard\Services\DashboardService;
use App\Services\RedisService;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
        // Não atribuir a $this->service porque DashboardService não implementa IService
    }

    /**
     * API: Obter dados do dashboard
     */
    public function index()
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
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

