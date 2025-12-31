<?php

namespace App\Modules\Orcamento\UseCases\Dashboard;

use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use Illuminate\Http\JsonResponse;

class ObtenerDashboardCompletoAction
{
    private DashboardDomainService $service;

    public function __construct(DashboardDomainService $service)
    {
        $this->service = $service;
    }

    public function execute(int $empresaId): JsonResponse
    {
        try {
            $dashboard = $this->service->obterDashboardCompleto($empresaId);
            
            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
