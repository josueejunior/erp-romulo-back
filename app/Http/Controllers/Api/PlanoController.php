<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    use HasDefaultActions;

    /**
     * API: Listar planos (Route::module)
     */
    public function list()
    {
        return $this->index();
    }

    /**
     * API: Buscar plano (Route::module)
     */
    public function get(Request $request, Plano $plano)
    {
        return $this->show($plano);
    }

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

