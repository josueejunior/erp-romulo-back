<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\CalendarioService;
use App\Services\RedisService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarioController extends BaseApiController
{
    protected CalendarioService $calendarioService;

    public function __construct(CalendarioService $calendarioService)
    {
        $this->calendarioService = $calendarioService;
    }

    /**
     * Retorna calendário de disputas
     */
    public function disputas(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $dataInicio = $request->has('data_inicio') 
            ? Carbon::parse($request->data_inicio) 
            : Carbon::now()->startOfMonth();
        
        $dataFim = $request->has('data_fim') 
            ? Carbon::parse($request->data_fim) 
            : Carbon::now()->endOfMonth();

        $tenantId = tenancy()->tenant?->id;
        $mes = $dataInicio->month;
        $ano = $dataInicio->year;

        // Tentar obter do cache primeiro (com empresa_id no cache key)
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "calendario_{$tenantId}_{$empresa->id}_{$mes}_{$ano}";
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json([
                    'data' => $cached,
                    'total' => count($cached),
                ]);
            }
        }

        $calendario = $this->calendarioService->getCalendarioDisputas($dataInicio, $dataFim, $empresa->id);

        // Salvar no cache se disponível (com empresa_id no cache key)
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "calendario_{$tenantId}_{$empresa->id}_{$mes}_{$ano}";
            RedisService::set($cacheKey, $calendario->toArray(), 1800); // Cache por 30 minutos
        }

        return response()->json([
            'data' => $calendario,
            'total' => $calendario->count(),
        ]);
    }

    /**
     * Retorna calendário de julgamento
     */
    public function julgamento(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $dataInicio = $request->has('data_inicio') 
            ? Carbon::parse($request->data_inicio) 
            : null;
        
        $dataFim = $request->has('data_fim') 
            ? Carbon::parse($request->data_fim) 
            : null;

        $calendario = $this->calendarioService->getCalendarioJulgamento($dataInicio, $dataFim, $empresa->id);

        return response()->json([
            'data' => $calendario,
            'total' => $calendario->count(),
        ]);
    }

    /**
     * Retorna avisos urgentes
     */
    public function avisosUrgentes()
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $avisos = $this->calendarioService->getAvisosUrgentes($empresa->id);

        return response()->json([
            'data' => $avisos,
            'total' => count($avisos),
        ]);
    }
}





