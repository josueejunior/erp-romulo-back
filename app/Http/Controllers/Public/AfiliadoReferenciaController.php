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

    /**
     * Busca cupom automático para usuário autenticado
     * Verifica se há referência de afiliado vinculada ao tenant
     */
    public function buscarCupomAutomatico(Request $request): JsonResponse
    {
        $tenantId = tenancy()->tenant?->id;
        
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant não encontrado.',
            ], 404);
        }

        // Buscar referência de afiliado vinculada ao tenant
        $referencia = \App\Models\AfiliadoReferencia::where('tenant_id', $tenantId)
            ->where('cadastro_concluido', true)
            ->where('cupom_aplicado', false)
            ->with('afiliado')
            ->first();

        if (!$referencia || !$referencia->afiliado) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum cupom disponível.',
            ], 404);
        }

        // Verificar se CNPJ já usou cupom
        $empresa = \App\Models\Empresa::first();
        if ($empresa && $empresa->cnpj) {
            $cnpjLimpo = preg_replace('/\D/', '', $empresa->cnpj);
            $jaUsou = \App\Models\AfiliadoReferencia::where('cnpj', $cnpjLimpo)
                ->where('cupom_aplicado', true)
                ->exists();
            
            if ($jaUsou) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupom já foi utilizado para este CNPJ.',
                ], 400);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cupom_codigo' => $referencia->afiliado->codigo,
                'afiliado_nome' => $referencia->afiliado->nome,
                'desconto_percentual' => $referencia->afiliado->percentual_desconto ?? 30,
                'mensagem' => "Você recebeu um cupom exclusivo de {$referencia->afiliado->percentual_desconto}% por indicação de {$referencia->afiliado->nome}.",
            ],
        ]);
    }
}

