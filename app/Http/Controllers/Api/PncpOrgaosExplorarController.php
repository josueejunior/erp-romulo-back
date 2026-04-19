<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\PermissionHelper;
use App\Services\Pncp\PncpOrgaosExplorarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * GET …/pncp/explorar-orgaos e GET …/orgaos/pncp/explorar
 *
 * Controller dedicado (invokable) para evitar 404 em ambientes com route cache
 * ou resolução de dependências do {@see \App\Modules\Orgao\Controllers\OrgaoController}.
 */
final class PncpOrgaosExplorarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! PermissionHelper::canCreateProcess()) {
            return response()->json(['message' => 'Sem permissão para consultar o PNCP.'], 403);
        }

        $validator = Validator::make($request->query(), [
            'uf' => ['nullable', 'string', 'size:2'],
            'codigo_ibge' => ['nullable', 'string', 'max:12'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'texto' => ['nullable', 'string', 'max:200'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'tamanho_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
            'data_inicial' => ['nullable', 'date_format:Y-m-d'],
            'data_final' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicial'],
            'codigo_modalidade' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $q = $validator->validated();
        $uf = isset($q['uf']) ? strtoupper(trim((string) $q['uf'])) : '';
        $cnpjLimpo = preg_replace('/\D/', '', (string) ($q['cnpj'] ?? '')) ?? '';

        if (strlen($cnpjLimpo) !== 14 && strlen($uf) !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Informe a UF (sigla com 2 letras) ou um CNPJ completo do órgão (14 dígitos) para buscar no PNCP.',
            ], 422);
        }

        $dias = max(7, min(365, (int) config('pncp.explorar_dias', 90)));
        $dataFinal = $q['data_final'] ?? now()->format('Y-m-d');
        $dataInicial = $q['data_inicial'] ?? now()->subDays($dias)->format('Y-m-d');
        $modalidade = (int) ($q['codigo_modalidade'] ?? config('pncp.explorar_codigo_modalidade', 6));

        try {
            $svc = PncpOrgaosExplorarService::fromConfig();
            $result = $svc->explorar([
                'data_inicial' => $dataInicial,
                'data_final' => $dataFinal,
                'codigo_modalidade' => $modalidade,
                'pagina' => (int) ($q['pagina'] ?? 1),
                'tamanho_pagina' => (int) ($q['tamanho_pagina'] ?? 50),
                'uf' => strlen($uf) === 2 ? $uf : null,
                'codigo_ibge' => $q['codigo_ibge'] ?? null,
                'cnpj' => strlen($cnpjLimpo) === 14 ? $cnpjLimpo : null,
                'texto' => $q['texto'] ?? null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $raw = $result['raw'];

        return response()->json([
            'success' => true,
            'data' => $result['itens'],
            'meta' => [
                'fonte' => 'pncp',
                'data_inicial' => $dataInicial,
                'data_final' => $dataFinal,
                'codigo_modalidade' => $modalidade,
                'pncp_pagina' => $raw['numeroPagina'] ?? null,
                'pncp_total_paginas' => $raw['totalPaginas'] ?? null,
                'pncp_total_registros' => $raw['totalRegistros'] ?? null,
                'orgaos_distintos_nesta_resposta' => count($result['itens']),
            ],
        ]);
    }
}
