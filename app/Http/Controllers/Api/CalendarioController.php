<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarioService;
use App\Services\RedisService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarioController extends Controller
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
        $dataInicio = $request->has('data_inicio') 
            ? Carbon::parse($request->data_inicio) 
            : Carbon::now()->startOfMonth();
        
        $dataFim = $request->has('data_fim') 
            ? Carbon::parse($request->data_fim) 
            : Carbon::now()->endOfMonth();

        $tenantId = tenancy()->tenant?->id;
        $mes = $dataInicio->month;
        $ano = $dataInicio->year;

        // Tentar obter do cache primeiro
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::getCalendario($tenantId, $mes, $ano);
            if ($cached !== null) {
                return response()->json([
                    'data' => $cached,
                    'total' => count($cached),
                ]);
            }
        }

        $calendario = $this->calendarioService->getCalendarioDisputas($dataInicio, $dataFim);

        // Salvar no cache se disponível
        if ($tenantId && RedisService::isAvailable()) {
            RedisService::cacheCalendario($tenantId, $mes, $ano, $calendario, 1800); // Cache por 30 minutos
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
        $dataInicio = $request->has('data_inicio') 
            ? Carbon::parse($request->data_inicio) 
            : null;
        
        $dataFim = $request->has('data_fim') 
            ? Carbon::parse($request->data_fim) 
            : null;

        $calendario = $this->calendarioService->getCalendarioJulgamento($dataInicio, $dataFim);

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
        $avisos = $this->calendarioService->getAvisosUrgentes();

        return response()->json([
            'data' => $avisos,
            'total' => count($avisos),
        ]);
    }
}




