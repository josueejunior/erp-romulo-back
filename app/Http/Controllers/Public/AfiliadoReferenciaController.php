<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Afiliado\UseCases\RastrearReferenciaAfiliadoUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para rastreamento de referência de afiliado
 */
class AfiliadoReferenciaController extends Controller
{
    public function __construct(
        private readonly RastrearReferenciaAfiliadoUseCase $rastrearReferenciaAfiliadoUseCase,
    ) {}

    /**
     * Rastreia uma referência de afiliado
     * 
     * Chamado quando o usuário acessa o site com ?ref=afiliado
     */
    public function rastrear(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string|max:50',
            'session_id' => 'nullable|string|max:255',
        ]);

        $referencia = $this->rastrearReferenciaAfiliadoUseCase->executar(
            referenciaCode: $request->input('ref'),
            sessionId: $request->input('session_id') ?? $request->session()->getId(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            email: $request->input('email'),
            metadata: [
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
            ],
        );

        if (!$referencia) {
            return response()->json([
                'success' => false,
                'message' => 'Código de afiliado inválido ou inativo.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referencia_id' => $referencia->id,
                'afiliado_id' => $referencia->afiliado_id,
                'session_id' => $referencia->session_id,
            ],
        ]);
    }

    /**
     * Verifica se um CNPJ já usou cupom
     */
    public function verificarCnpjJaUsouCupom(Request $request): JsonResponse
    {
        $request->validate([
            'cnpj' => 'required|string',
        ]);

        $jaUsou = $this->rastrearReferenciaAfiliadoUseCase->cnpjJaUsouCupom(
            $request->input('cnpj')
        );

        return response()->json([
            'success' => true,
            'ja_usou_cupom' => $jaUsou,
        ]);
    }

    /**
     * Busca referência ativa por sessão ou email
     */
    public function buscarReferenciaAtiva(Request $request): JsonResponse
    {
        $referencia = $this->rastrearReferenciaAfiliadoUseCase->buscarReferenciaAtiva(
            sessionId: $request->input('session_id') ?? $request->session()->getId(),
            email: $request->input('email'),
        );

        if (!$referencia) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma referência ativa encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referencia_id' => $referencia->id,
                'afiliado_id' => $referencia->afiliado_id,
                'referencia_code' => $referencia->referencia_code,
                'cadastro_concluido' => $referencia->cadastro_concluido,
                'cupom_aplicado' => $referencia->cupom_aplicado,
            ],
        ]);
    }
}

