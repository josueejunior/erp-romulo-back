<?php

namespace App\Modules\Orcamento\UseCases\Dashboard;

use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use Illuminate\Http\JsonResponse;

class ObtenerMetricasDashboardAction
{
    private DashboardDomainService $service;

    public function __construct(DashboardDomainService $service)
    {
        $this->service = $service;
    }

    public function execute(int $empresaId): JsonResponse
    {
        try {
            $metricas = $this->service->obterMetricas($empresaId);
            
            return response()->json([
                'success' => true,
                'data' => $metricas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
