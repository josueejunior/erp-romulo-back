<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    /**
     * Listar todos os planos disponíveis
     */
    public function index()
    {
        $planos = Plano::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('preco_mensal')
            ->get();

        return response()->json([
            'data' => $planos
        ]);
    }

    /**
     * Mostrar detalhes de um plano
     */
    public function show(Plano $plano)
    {
        return response()->json($plano);
    }
}
