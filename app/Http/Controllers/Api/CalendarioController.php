<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarioService;
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
            : null;
        
        $dataFim = $request->has('data_fim') 
            ? Carbon::parse($request->data_fim) 
            : null;

        $calendario = $this->calendarioService->getCalendarioDisputas($dataInicio, $dataFim);

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




