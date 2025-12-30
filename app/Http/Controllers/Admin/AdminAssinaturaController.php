<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Controller Admin para gerenciar assinaturas de todos os tenants
 */
class AdminAssinaturaController extends Controller
{
    /**
     * Listar todas as assinaturas de todos os tenants
     */
    public function index(Request $request)
    {
        try {
            $filtros = [
                'tenant_id' => $request->tenant_id,
                'status' => $request->status,
                'search' => $request->search,
                'per_page' => $request->per_page ?? 15,
            ];

            // Buscar todos os tenants
            $tenants = Tenant::where('status', '!=', 'inativa')
                ->orWhereNull('status')
                ->get();

            $todasAssinaturas = [];

            foreach ($tenants as $tenant) {
                try {
                    // Inicializar contexto do tenant
                    $jaInicializado = tenancy()->initialized;
                    if (!$jaInicializado) {
                        tenancy()->initialize($tenant);
                    }

                    // Buscar assinatura atual do tenant
                    $assinatura = null;
                    
                    // Se o tenant tem assinatura_atual_id, buscar por ele
                    if ($tenant->assinatura_atual_id) {
                        $assinatura = Assinatura::with('plano')
                            ->where('id', $tenant->assinatura_atual_id)
                            ->first();
                    }

                    // Se não encontrou, buscar a mais recente
                    if (!$assinatura) {
                        $assinatura = Assinatura::with('plano')
                            ->where('tenant_id', $tenant->id)
                            ->where('status', '!=', 'cancelada')
                            ->orderBy('data_fim', 'desc')
                            ->orderBy('criado_em', 'desc')
                            ->first();
                    }

                    if ($assinatura) {
                        $todasAssinaturas[] = [
                            'id' => $assinatura->id,
                            'tenant_id' => $tenant->id,
                            'tenant_nome' => $tenant->razao_social,
                            'tenant_cnpj' => $tenant->cnpj,
                            'plano_id' => $assinatura->plano_id,
                            'plano_nome' => $assinatura->plano?->nome ?? 'N/A',
                            'status' => $assinatura->status,
                            'valor_pago' => $assinatura->valor_pago ?? 0,
                            'data_inicio' => $assinatura->data_inicio?->format('Y-m-d'),
                            'data_fim' => $assinatura->data_fim?->format('Y-m-d'),
                            'metodo_pagamento' => $assinatura->metodo_pagamento ?? 'N/A',
                            'transacao_id' => $assinatura->transacao_id,
                            'dias_restantes' => $assinatura->diasRestantes(),
                        ];
                    }

                    // Finalizar contexto do tenant
                    if (!$jaInicializado && tenancy()->initialized) {
                        tenancy()->end();
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao buscar assinatura do tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Finalizar contexto se ainda estiver inicializado
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }

            // Aplicar filtros
            if ($filtros['tenant_id']) {
                $todasAssinaturas = array_filter($todasAssinaturas, function ($a) use ($filtros) {
                    return $a['tenant_id'] == $filtros['tenant_id'];
                });
            }

            if ($filtros['status']) {
                $todasAssinaturas = array_filter($todasAssinaturas, function ($a) use ($filtros) {
                    return $a['status'] === $filtros['status'];
                });
            }

            if ($filtros['search']) {
                $search = strtolower($filtros['search']);
                $todasAssinaturas = array_filter($todasAssinaturas, function ($a) use ($search) {
                    return str_contains(strtolower($a['tenant_nome']), $search) ||
                           str_contains(strtolower($a['plano_nome']), $search);
                });
            }

            // Reindexar array
            $todasAssinaturas = array_values($todasAssinaturas);

            // Paginação manual
            $total = count($todasAssinaturas);
            $perPage = $filtros['per_page'];
            $currentPage = $request->page ?? 1;
            $offset = ($currentPage - 1) * $perPage;
            $paginated = array_slice($todasAssinaturas, $offset, $perPage);

            return response()->json([
                'data' => $paginated,
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar assinaturas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao listar assinaturas.'], 500);
        }
    }

    /**
     * Buscar assinatura específica
     */
    public function show(int $tenantId, int $assinaturaId)
    {
        try {
            $tenant = Tenant::find($tenantId);
            
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado.'], 404);
            }

            // Inicializar contexto do tenant
            $jaInicializado = tenancy()->initialized;
            if (!$jaInicializado) {
                tenancy()->initialize($tenant);
            }

            try {
                $assinatura = Assinatura::with('plano')
                    ->where('id', $assinaturaId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if (!$assinatura) {
                    return response()->json(['message' => 'Assinatura não encontrada.'], 404);
                }

                $data = [
                    'id' => $assinatura->id,
                    'tenant_id' => $tenant->id,
                    'tenant_nome' => $tenant->razao_social,
                    'plano_id' => $assinatura->plano_id,
                    'plano_nome' => $assinatura->plano?->nome ?? 'N/A',
                    'status' => $assinatura->status,
                    'valor_pago' => $assinatura->valor_pago ?? 0,
                    'data_inicio' => $assinatura->data_inicio?->format('Y-m-d'),
                    'data_fim' => $assinatura->data_fim?->format('Y-m-d'),
                    'metodo_pagamento' => $assinatura->metodo_pagamento ?? 'N/A',
                    'transacao_id' => $assinatura->transacao_id,
                    'dias_restantes' => $assinatura->diasRestantes(),
                    'observacoes' => $assinatura->observacoes,
                ];

                return ApiResponse::item($data);
            } finally {
                if (!$jaInicializado && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar assinatura', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
            ]);
            return response()->json(['message' => 'Erro ao buscar assinatura.'], 500);
        }
    }

    /**
     * Listar tenants para filtro
     */
    public function tenants()
    {
        try {
            $tenants = Tenant::select('id', 'razao_social', 'cnpj')
                ->where('status', '!=', 'inativa')
                ->orWhereNull('status')
                ->orderBy('razao_social')
                ->get();

            return response()->json([
                'data' => $tenants->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'razao_social' => $tenant->razao_social,
                        'cnpj' => $tenant->cnpj,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar tenants', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empresas.'], 500);
        }
    }
}

