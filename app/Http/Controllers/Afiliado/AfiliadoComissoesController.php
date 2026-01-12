<?php

declare(strict_types=1);

namespace App\Http\Controllers\Afiliado;

use App\Http\Controllers\Controller;
use App\Application\Afiliado\UseCases\BuscarComissoesAfiliadoUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para comissões de afiliados (área do afiliado)
 */
class AfiliadoComissoesController extends Controller
{
    public function __construct(
        private readonly BuscarComissoesAfiliadoUseCase $buscarComissoesAfiliadoUseCase,
    ) {}

    /**
     * Lista comissões do afiliado autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // TODO: Buscar afiliado_id do usuário autenticado
            // Por enquanto, vamos usar um parâmetro (depois integrar com auth)
            $afiliadoId = $request->input('afiliado_id');
            
            if (!$afiliadoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Afiliado não identificado.',
                ], 400);
            }

            $comissoes = $this->buscarComissoesAfiliadoUseCase->executar(
                afiliadoId: $afiliadoId,
                status: $request->input('status'),
                dataInicio: $request->input('data_inicio'),
                dataFim: $request->input('data_fim'),
                perPage: $request->input('per_page', 15),
                page: $request->input('page', 1),
            );

            return response()->json([
                'success' => true,
                'data' => $comissoes,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar comissões do afiliado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar comissões.',
            ], 500);
        }
    }

    /**
     * Busca resumo de comissões (estatísticas)
     */
    public function resumo(Request $request): JsonResponse
    {
        try {
            $afiliadoId = $request->input('afiliado_id');
            
            if (!$afiliadoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Afiliado não identificado.',
                ], 400);
            }

            $resumo = $this->buscarComissoesAfiliadoUseCase->buscarResumo($afiliadoId);

            return response()->json([
                'success' => true,
                'data' => $resumo,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar resumo de comissões', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar resumo.',
            ], 500);
        }
    }
}





