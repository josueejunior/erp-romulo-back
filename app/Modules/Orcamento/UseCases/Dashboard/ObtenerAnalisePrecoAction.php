<?php

namespace App\Modules\Orcamento\UseCases\Dashboard;

use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use Illuminate\Http\JsonResponse;

class ObtenerAnalisePrecoAction
{
    private DashboardDomainService $service;

    public function __construct(DashboardDomainService $service)
    {
        $this->service = $service;
    }

    public function execute(int $empresaId): JsonResponse
    {
        try {
            $analise = $this->service->obterAnalisePrecos($empresaId);
            
            return response()->json([
                'success' => true,
                'data' => $analise
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
