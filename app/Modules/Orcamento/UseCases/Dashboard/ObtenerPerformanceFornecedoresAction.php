<?php

namespace App\Modules\Orcamento\UseCases\Dashboard;

use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use Illuminate\Http\JsonResponse;

class ObtenerPerformanceFornecedoresAction
{
    private DashboardDomainService $service;

    public function __construct(DashboardDomainService $service)
    {
        $this->service = $service;
    }

    public function execute(int $empresaId): JsonResponse
    {
        try {
            $performance = $this->service->obterPerformanceFornecedores($empresaId);
            
            return response()->json([
                'success' => true,
                'data' => $performance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
